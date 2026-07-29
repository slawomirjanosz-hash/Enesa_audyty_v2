<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use App\Models\Offer;
use App\Models\OfferDelegation;
use App\Models\OfferMessage;
use App\Models\OfferRequest;
use App\Models\OfferSavedTemplate;
use App\Models\OfferTemplateType;
use App\Models\User;
use App\Services\AuditorAccessService;
use App\Services\OfferPricingSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Style\Language;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $isTemplate = $request->boolean('template');
        $access = app(AuditorAccessService::class);
        $user = $request->user();
        $query = $access->scopeByCompanyAccess(
            Offer::with(['company', 'assignedUser', 'createdBy']),
            $user,
            'can_view_offers'
        )
            ->where('is_template', $isTemplate)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $offers = $query->paginate(20)->withQueryString();

        $offers->each(function (Offer $offer) use ($user) {
            if (! $user->can('viewPrices', $offer)) {
                $this->removePriceData($offer);
            }
        });

        $visibleOffers = $access->scopeByCompanyAccess(
            Offer::query(),
            $user,
            'can_view_offers'
        )->where('is_template', $isTemplate);

        $stats = [
            'w_toku'        => (clone $visibleOffers)->where('status', 'w_toku')->count(),
            'wygrana'       => (clone $visibleOffers)->where('status', 'wygrana')->count(),
            'przegrana'     => (clone $visibleOffers)->where('status', 'przegrana')->count(),
            'zarchiwizowana'=> (clone $visibleOffers)->where('status', 'zarchiwizowana')->count(),
        ];

        return view('offers.index', compact('offers', 'stats'));
    }

    public function create(Request $request): View
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $companySettings     = \App\Models\CompanySettings::first();
        $companies           = Company::orderBy('name')->get();
        $users               = User::role(['superadmin', 'admin', 'auditor_senior', 'auditor'])->orderBy('name')->get();
        $offerTemplateTypes  = OfferTemplateType::where('is_active', true)
            ->with('offerTemplateVersions')
            ->get();

        $offerRequest = $request->filled('offer_request_id')
            ? OfferRequest::with(['company', 'offerFormTemplate'])->find($request->offer_request_id)
            : null;

        $pricingSuggestion = $offerRequest
            ? app(OfferPricingSuggestionService::class)->forOfferRequest($offerRequest)
            : null;

        $offerTemplates = Offer::where('is_template', true)
            ->orderBy('offer_title')
            ->get();

        $suggestedNumber = Offer::generateNumber();
        $numberExists    = Offer::where('offer_number', $suggestedNumber)->exists();

        return view('offers.create', compact(
            'companySettings',
            'companies',
            'users',
            'offerTemplateTypes',
            'offerRequest',
            'pricingSuggestion',
            'offerTemplates',
            'suggestedNumber',
            'numberExists',
        ));
    }

    public function getTemplate(Offer $offer): JsonResponse
    {
        $this->authorize('view', $offer);
        $this->authorize('viewPrices', $offer);

        return response()->json([
            'offer_title'      => $offer->offer_title,
            'content_subject'  => $offer->content_subject,
            'content_scope'    => $offer->content_scope,
            'content_deadline' => $offer->content_deadline,
            'content_payment'  => $offer->content_payment,
            'price_sections'   => $offer->price_sections,
            'text_sections'    => $offer->text_sections,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);
        $data = $request->validate([
            'company_id'                => ['required', 'exists:companies,id'],
            'offer_number'              => ['required', 'string', 'unique:offers,offer_number'],
            'offer_slug'                => ['nullable', 'string', 'max:255'],
            'offer_title'               => ['nullable', 'string', 'max:500'],
            'status'                    => ['required', 'in:w_toku,wygrana,przegrana,zarchiwizowana'],
            'assigned_user_id'          => ['nullable', 'exists:users,id'],
            'kwota_netto'               => ['nullable', 'numeric', 'min:0'],
            'valid_until'               => ['nullable', 'date'],
            'created_at'                => ['nullable', 'date'],
            'notes'                     => ['nullable', 'string'],
            'offer_template_version_id' => ['nullable', 'exists:offer_template_versions,id'],
            'offer_request_id'          => ['nullable', 'exists:offer_requests,id'],
            // Rich content
            'content_subject'           => ['nullable', 'string'],
            'content_scope'             => ['nullable', 'string'],
            'content_deadline'          => ['nullable', 'string'],
            'content_payment'           => ['nullable', 'string'],
            'show_unit_prices'          => ['nullable'],
            'price_sections'            => ['nullable', 'string'],
            'text_sections'             => ['nullable', 'string'],
            'delegations'               => ['nullable', 'string'],
            // Delegation
            'km_do_klienta'             => ['nullable', 'numeric', 'min:0'],
            'stawka_km'                 => ['nullable', 'numeric', 'min:0'],
            'czas_dojazdu_min'          => ['nullable', 'numeric', 'min:0'],
            'liczba_wyjazdow'           => ['required', 'numeric', 'min:1'],
            'czy_kilkudniowy'           => ['boolean'],
            'liczba_noc'                => ['required', 'numeric', 'min:0'],
            'liczba_osob'               => ['required', 'numeric', 'min:1'],
            'stawka_noc'                => ['required', 'numeric', 'min:0'],
        ]);

        $slug = $request->filled('offer_slug')
            ? '_' . Str::slug($request->offer_slug, '_')
            : '';

        $priceSections = null;
        if ($request->filled('price_sections')) {
            $decoded = json_decode($request->input('price_sections'), true);
            $priceSections = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $textSections = null;
        if ($request->filled('text_sections')) {
            $decoded = json_decode($request->input('text_sections'), true);
            $textSections = json_last_error() === JSON_ERROR_NONE
                ? $this->normalizeTextSections($decoded)
                : null;
        }

        $delegations = null;
        if ($request->filled('delegations')) {
            $decoded = json_decode($request->input('delegations'), true);
            $delegations = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $offer = Offer::create([
            'company_id'                => $data['company_id'],
            'offer_number'              => $data['offer_number'],
            'offer_slug'                => $data['offer_slug'] ?? null,
            'offer_full_number'         => $data['offer_number'] . $slug,
            'offer_title'               => $data['offer_title'] ?? null,
            'status'                    => $data['status'],
            'assigned_user_id'          => $data['assigned_user_id'] ?? null,
            'created_by_id'             => auth()->id(),
            'kwota_netto'               => $data['kwota_netto'] ?? null,
            'valid_until'               => $data['valid_until'] ?? null,
            'notes'                     => $data['notes'] ?? null,
            'additional_description'    => $data['additional_description'] ?? null,
            'offer_template_version_id' => $data['offer_template_version_id'] ?? null,
            'offer_request_id'          => $data['offer_request_id'] ?? null,
            'content_subject'           => $data['content_subject'] ?? null,
            'content_scope'             => $data['content_scope'] ?? null,
            'content_deadline'          => $data['content_deadline'] ?? null,
            'content_payment'           => $data['content_payment'] ?? null,
            'show_unit_prices'          => $request->input('show_unit_prices') === '1',
            'price_sections'            => $priceSections,
            'text_sections'             => $textSections,
            'delegations'               => $delegations,
        ]);

        if (!empty($data['created_at'])) {
            $offer->created_at = \Carbon\Carbon::parse($data['created_at']);
            $offer->save();
        }

        OfferDelegation::create([
            'offer_id'         => $offer->id,
            'km_do_klienta'    => $data['km_do_klienta'] ?? null,
            'stawka_km'        => $data['stawka_km'] ?? 1.10,
            'czas_dojazdu_min' => $data['czas_dojazdu_min'] ?? null,
            'liczba_wyjazdow'  => $data['liczba_wyjazdow'],
            'czy_kilkudniowy'  => $request->boolean('czy_kilkudniowy'),
            'liczba_noc'       => $data['liczba_noc'],
            'liczba_osob'      => $data['liczba_osob'],
            'stawka_noc'       => $data['stawka_noc'],
        ]);

        if (! empty($data['offer_request_id'])) {
            OfferRequest::where('id', $data['offer_request_id'])
                ->update(['status' => 'w_toku']);
        }

        return redirect()->route('offers.show', $offer)
            ->with('success', 'Oferta ' . $offer->offer_full_number . ' została utworzona.');
    }

    public function show(Offer $offer): View
    {
        $this->authorize('view', $offer);
        $offer->load([
            'company',
            'assignedUser',
            'createdBy',
            'offerTemplateVersion',
            'offerRequest',
            'offerDelegation',
            'offerMessages.user',
        ]);

        if (! auth()->user()->can('viewPrices', $offer)) {
            $this->removePriceData($offer);
        }

        return view('offers.show', compact('offer'));
    }

    public function edit(Offer $offer): View
    {
        $this->authorize('update', $offer);
        $companySettings     = \App\Models\CompanySettings::first();
        $companies           = Company::orderBy('name')->get();
        $users               = User::role(['superadmin', 'admin', 'auditor_senior', 'auditor'])->orderBy('name')->get();
        $offerTemplateTypes  = OfferTemplateType::where('is_active', true)
            ->with('offerTemplateVersions')
            ->get();
        $offerRequest = $offer->offerRequest;

        $suggestedNumber = $offer->offer_number;
        $numberExists    = false;

        return view('offers.edit', compact(
            'companySettings',
            'offer',
            'companies',
            'users',
            'offerTemplateTypes',
            'offerRequest',
            'suggestedNumber',
            'numberExists',
        ));
    }

    public function update(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);
        $data = $request->validate([
            'company_id'                => $offer->is_template
                ? ['nullable', 'exists:companies,id']
                : ['required', 'exists:companies,id'],
            'offer_number'              => ['required', 'string', 'unique:offers,offer_number,' . $offer->id],
            'offer_slug'                => ['nullable', 'string', 'max:255'],
            'offer_title'               => ['nullable', 'string', 'max:500'],
            'status'                    => ['required', 'in:w_toku,wygrana,przegrana,zarchiwizowana'],
            'assigned_user_id'          => ['nullable', 'exists:users,id'],
            'kwota_netto'               => ['nullable', 'numeric', 'min:0'],
            'valid_until'               => ['nullable', 'date'],
            'created_at'                => ['nullable', 'date'],
            'notes'                     => ['nullable', 'string'],
            'offer_template_version_id' => ['nullable', 'exists:offer_template_versions,id'],
            'offer_request_id'          => ['nullable', 'exists:offer_requests,id'],
            // Rich content
            'content_subject'           => ['nullable', 'string'],
            'content_scope'             => ['nullable', 'string'],
            'content_deadline'          => ['nullable', 'string'],
            'content_payment'           => ['nullable', 'string'],
            'show_unit_prices'          => ['nullable'],
            'price_sections'            => ['nullable', 'string'],
            'text_sections'             => ['nullable', 'string'],
            'delegations'               => ['nullable', 'string'],
            // Delegation
            'km_do_klienta'             => ['nullable', 'numeric', 'min:0'],
            'stawka_km'                 => ['nullable', 'numeric', 'min:0'],
            'czas_dojazdu_min'          => ['nullable', 'numeric', 'min:0'],
            'liczba_wyjazdow'           => ['required', 'numeric', 'min:1'],
            'czy_kilkudniowy'           => ['boolean'],
            'liczba_noc'                => ['required', 'numeric', 'min:0'],
            'liczba_osob'               => ['required', 'numeric', 'min:1'],
            'stawka_noc'                => ['required', 'numeric', 'min:0'],
        ]);

        $slug = $request->filled('offer_slug')
            ? '_' . Str::slug($request->offer_slug, '_')
            : '';

        $priceSections = null;
        if ($request->filled('price_sections')) {
            $decoded = json_decode($request->input('price_sections'), true);
            $priceSections = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $textSections = null;
        if ($request->filled('text_sections')) {
            $decoded = json_decode($request->input('text_sections'), true);
            $textSections = json_last_error() === JSON_ERROR_NONE
                ? $this->normalizeTextSections($decoded)
                : null;
        }

        $delegations = null;
        if ($request->filled('delegations')) {
            $decoded = json_decode($request->input('delegations'), true);
            $delegations = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $offer->update([
            'company_id'                => $offer->is_template ? null : $data['company_id'],
            'offer_number'              => $data['offer_number'],
            'offer_slug'                => $data['offer_slug'] ?? null,
            'offer_full_number'         => $data['offer_number'] . $slug,
            'offer_title'               => $data['offer_title'] ?? null,
            'status'                    => $data['status'],
            'assigned_user_id'          => $data['assigned_user_id'] ?? null,
            'kwota_netto'               => $data['kwota_netto'] ?? null,
            'valid_until'               => $data['valid_until'] ?? null,
            'notes'                     => $data['notes'] ?? null,
            'additional_description'    => $data['additional_description'] ?? null,
            'offer_template_version_id' => $data['offer_template_version_id'] ?? null,
            'offer_request_id'          => $data['offer_request_id'] ?? null,
            'content_subject'           => $data['content_subject'] ?? null,
            'content_scope'             => $data['content_scope'] ?? null,
            'content_deadline'          => $data['content_deadline'] ?? null,
            'content_payment'           => $data['content_payment'] ?? null,
            'show_unit_prices'          => $request->input('show_unit_prices') === '1',
            'price_sections'            => $priceSections,
            'text_sections'             => $textSections,
            'delegations'               => $delegations,
        ]);

        if (!empty($data['created_at'])) {
            $offer->created_at = \Carbon\Carbon::parse($data['created_at']);
            $offer->save();
        }

        $delegation = $offer->offerDelegation ?? new OfferDelegation(['offer_id' => $offer->id]);
        $delegation->fill([
            'km_do_klienta'    => $data['km_do_klienta'] ?? null,
            'stawka_km'        => $data['stawka_km'] ?? 1.10,
            'czas_dojazdu_min' => $data['czas_dojazdu_min'] ?? null,
            'liczba_wyjazdow'  => $data['liczba_wyjazdow'],
            'czy_kilkudniowy'  => $request->boolean('czy_kilkudniowy'),
            'liczba_noc'       => $data['liczba_noc'],
            'liczba_osob'      => $data['liczba_osob'],
            'stawka_noc'       => $data['stawka_noc'],
        ])->save();

        return redirect()->route('offers.show', $offer)
            ->with('success', 'Oferta ' . $offer->offer_full_number . ' została zaktualizowana.');
    }

    public function updateStatus(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);
        $data = $request->validate([
            'status' => ['required', 'in:w_toku,wygrana,przegrana,zarchiwizowana'],
            'won_as' => ['nullable', 'in:audyt,projekt,inne'],
        ]);

        $offer->update($data);

        return redirect()->back()->with('success', 'Status oferty został zaktualizowany.');
    }

    public function storeMessage(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);
        $data = $request->validate([
            'tresc'       => ['required', 'string'],
            'is_internal' => ['boolean'],
        ]);

        OfferMessage::create([
            'offer_id'    => $offer->id,
            'user_id'     => auth()->id(),
            'tresc'       => $data['tresc'],
            'is_internal' => $request->boolean('is_internal'),
        ]);

        return redirect()->back()->with('success', 'Wiadomość została dodana.')->withFragment('messages');
    }

    public function pdf(Request $request, Offer $offer): \Illuminate\Http\Response
    {
        $this->authorize('view', $offer);
        $this->authorize('viewPrices', $offer);
        try {
            $mpdf = $this->buildOfferPdf($offer, $request->has('unit') ? $request->boolean('unit') : null);
            $filename = 'oferta-' . $offer->fullNumber() . '.pdf';

            return response($mpdf->Output($filename, 'S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('mPDF generation failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            throw $e;
        }
    }

    public function saveToStorage(Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);
        $offer->loadMissing('company');

        $mpdf   = $this->buildOfferPdf($offer);
        $binary = $mpdf->Output('', 'S');

        $safeNumber   = str_replace(['/', '\\', ' '], '_', $offer->fullNumber());
        $filename     = 'oferta_' . $safeNumber . '.pdf';
        $companyFolder = $offer->company?->folderSlug() ?? ('firma_' . $offer->company_id);
        $relativePath = 'documents/' . $companyFolder . '/' . $filename;

        \Illuminate\Support\Facades\Storage::disk('local')->put($relativePath, $binary);

        Document::updateOrCreate(
            ['offer_id' => $offer->id, 'type' => 'offer_pdf'],
            [
                'company_id'        => $offer->company_id,
                'original_filename' => $filename,
                'stored_path'       => $relativePath,
                'mime_type'         => 'application/pdf',
                'size'              => strlen($binary),
                'uploaded_by'       => null,
            ]
        );

        return redirect()->back()->with('success', 'PDF oferty został zapisany w dokumentach firmy.');
    }

    private function buildOfferPdf(Offer $offer, ?bool $forceUnitPrices = null): \Mpdf\Mpdf
    {
        $offer->load(['company', 'assignedUser', 'offerDelegation']);
        $companySettings = \App\Models\CompanySettings::first();

        $offer->content_subject  = $this->cleanQuillHtml($offer->content_subject);
        $offer->content_scope    = $this->cleanQuillHtml($offer->content_scope);
        $offer->content_deadline = $this->cleanQuillHtml($offer->content_deadline);
        $offer->content_payment  = $this->cleanQuillHtml($offer->content_payment);

        if ($forceUnitPrices !== null) {
            $offer->show_unit_prices = $forceUnitPrices;
        }

        $logoBase64 = null;
        $logoPath   = public_path('Logo2.png');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $html = view('offers.pdf', compact('offer', 'companySettings', 'logoBase64'))->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode'                => 'utf-8',
            'format'              => 'A4',
            'margin_top'          => 12,
            'margin_bottom'       => 20,
            'margin_left'         => 15,
            'margin_right'        => 15,
            'setAutoTopMargin'    => false,
            'setAutoBottomMargin' => false,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    public function downloadWord(Offer $offer): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $offer);
        $this->authorize('viewPrices', $offer);
        $offer->load(['company', 'offerRequest']);

        $phpWord = new PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language('pl-PL'));
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $green       = '1A4D3A';
        $lightGreen  = 'F0F7F4';
        $borderColor = 'D8D8D8';
        $pageW       = Converter::cmToTwip(17); // 21 - 2 - 2

        $section = $phpWord->addSection([
            'marginTop'    => Converter::cmToTwip(1.8),
            'marginBottom' => Converter::cmToTwip(2),
            'marginLeft'   => Converter::cmToTwip(2),
            'marginRight'  => Converter::cmToTwip(2),
        ]);

        // ── HEADER: logo + numer oferty ──────────────────────────────────────
        $hTable = $section->addTable([
            'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0,
            'width' => $pageW, 'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        ]);
        $hRow = $hTable->addRow(Converter::cmToTwip(1.4));

        $logoCell = $hRow->addCell(Converter::cmToTwip(8), ['borderColor' => 'FFFFFF', 'borderSize' => 0]);
        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $logoCell->addImage($logoPath, ['width' => 110, 'height' => 36]);
        } else {
            $logoCell->addText('ENESA', ['bold' => true, 'size' => 18, 'color' => $green]);
        }

        $numCell = $hRow->addCell(Converter::cmToTwip(9), ['borderColor' => 'FFFFFF', 'borderSize' => 0, 'valign' => 'center']);
        $numCell->addText(
            'OFERTA NR ' . ($offer->offer_number ?? $offer->id),
            ['bold' => true, 'size' => 11, 'color' => $green],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]
        );
        $numCell->addText(
            'Data wystawienia: ' . ($offer->created_at?->format('d.m.Y') ?? '—'),
            ['size' => 9, 'color' => '888888'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]
        );

        // Linia pod headerem
        $section->addText('', [], [
            'borderBottomColor' => $green,
            'borderBottomSize'  => 8,
            'spaceAfter'        => Converter::pointToTwip(12),
        ]);

        // ── TYTUŁ OFERTY ─────────────────────────────────────────────────────
        if ($offer->offer_title) {
            $section->addText(
                $offer->offer_title,
                ['bold' => true, 'size' => 18, 'color' => '1A1A1A'],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => Converter::pointToTwip(4)]
            );
            $section->addText(
                'Oferta handlowa przygotowana przez ENESA Sp. z o.o.',
                ['size' => 10, 'color' => '888888', 'italic' => true],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => Converter::pointToTwip(14)]
            );
        }

        // ── WYSTAWCA / ODBIORCA ──────────────────────────────────────────────
        $pTable = $section->addTable([
            'borderSize'  => 6,
            'borderColor' => $borderColor,
            'cellMargin'  => 120,
            'width'       => $pageW,
            'unit'        => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        ]);
        $pRow = $pTable->addRow();

        $halfW = Converter::cmToTwip(8.3);

        $wCell = $pRow->addCell($halfW, ['bgColor' => $lightGreen, 'valign' => 'top']);
        $wCell->addText('WYSTAWCA OFERTY', ['size' => 8, 'color' => '7A9E90', 'bold' => true]);
        $wCell->addText('Enesa sp. z o. o.', ['bold' => true, 'size' => 11, 'color' => $green]);
        $wCell->addText('ul. Konarskiego 18C', ['size' => 10]);
        $wCell->addText('44-100 Gliwice', ['size' => 10]);
        $wCell->addText('NIP: 6312741198', ['size' => 10]);
        $wCell->addText('biuro@enesa.pl', ['size' => 10]);

        $oCell = $pRow->addCell($halfW, ['bgColor' => $lightGreen, 'valign' => 'top']);
        $oCell->addText('ODBIORCA OFERTY', ['size' => 8, 'color' => '7A9E90', 'bold' => true]);
        $oCell->addText($offer->company?->name ?? '—', ['bold' => true, 'size' => 11]);
        if ($offer->company?->address) $oCell->addText($offer->company->address, ['size' => 10]);
        if ($offer->company?->city)    $oCell->addText($offer->company->city, ['size' => 10]);
        if ($offer->company?->nip)     $oCell->addText('NIP: ' . $offer->company->nip, ['size' => 10]);
        if ($offer->company?->email)   $oCell->addText($offer->company->email, ['size' => 10]);

        $section->addTextBreak(1);

        // ── HELPER: nagłówek sekcji ───────────────────────────────────────────
        $addSectionHeader = function (string $label) use ($section, $green) {
            $section->addText(strtoupper($label), ['size' => 9, 'color' => $green, 'bold' => true], [
                'borderBottomColor' => 'D0D0D0',
                'borderBottomSize'  => 4,
                'spaceAfter'        => Converter::pointToTwip(6),
            ]);
        };

        // Tekst oferty jest wspólny dla PDF i DOCX. Starsze oferty, które nie
        // mają jeszcze JSON-a sekcji, zachowują dotychczasowy układ.
        $textSections = $this->normalizeTextSections($offer->text_sections ?? []);
        if (empty($textSections)) {
            $textSections = $this->normalizeTextSections([
                ['name' => 'Przedmiot oferty', 'content' => $offer->content_subject ?? '', 'placement' => 'before_price'],
                ['name' => 'Zakres prac', 'content' => $offer->content_scope ?? '', 'placement' => 'before_price'],
                ['name' => 'Termin realizacji', 'content' => $offer->content_deadline ?? '', 'placement' => 'after_price'],
                ['name' => 'Warunki płatności', 'content' => $offer->content_payment ?? '', 'placement' => 'after_price'],
            ]) ?? [];
        }

        $beforePriceSections = array_values(array_filter(
            $textSections,
            fn (array $item) => $item['placement'] === 'before_price'
        ));
        $afterPriceSections = array_values(array_filter(
            $textSections,
            fn (array $item) => $item['placement'] === 'after_price'
        ));

        $addTextSection = function (array $item) use ($section, $addSectionHeader): void {
            if (trim(strip_tags($item['content'])) === '') {
                return;
            }

            $addSectionHeader($item['name']);
            Html::addHtml($section, $item['content'], false, false);
            $section->addTextBreak(1);
        };

        foreach ($beforePriceSections as $textSection) {
            $addTextSection($textSection);
        }

        // ── WYCENA ───────────────────────────────────────────────────────────
        $priceSections = $offer->price_sections ?? [];
        $grandTotal = 0;

        if (!empty($priceSections)) {
            $addSectionHeader('Wycena');

            foreach ($priceSections as $ps) {
                if (count($priceSections) > 1 && !empty($ps['name'])) {
                    $section->addText(
                        $ps['name'],
                        ['bold' => true, 'size' => 11, 'color' => $green],
                        ['spaceBefore' => Converter::pointToTwip(6), 'spaceAfter' => Converter::pointToTwip(3)]
                    );
                }

                // Szerokości kolumn: 1+6.5+2+1.5+3+3 = 17 cm
                $colW = [
                    Converter::cmToTwip(1),
                    Converter::cmToTwip(6.5),
                    Converter::cmToTwip(2),
                    Converter::cmToTwip(1.5),
                    Converter::cmToTwip(3),
                    Converter::cmToTwip(3),
                ];

                $priceTable = $section->addTable([
                    'borderSize'    => 4,
                    'borderColor'   => 'E0E0E0',
                    'cellMargin'    => 80,
                    'width'         => $pageW,
                    'unit'          => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
                    'columnWidths'  => $colW,
                ]);

                $th = $priceTable->addRow(Converter::cmToTwip(0.8));
                foreach ([
                    ['#',               $colW[0]],
                    ['Opis',            $colW[1]],
                    ['Ilość',           $colW[2]],
                    ['Jedn.',           $colW[3]],
                    ['Cena jedn. (zł)', $colW[4]],
                    ['Razem (zł)',      $colW[5]],
                ] as [$h, $w]) {
                    $th->addCell($w, ['bgColor' => $green])
                       ->addText($h, ['bold' => true, 'size' => 9, 'color' => 'FFFFFF']);
                }

                $rows = $ps['rows'] ?? [];
                $sectionTotal = 0;
                foreach ($rows as $i => $row) {
                    $znarzutem   = (float)($row['z_narzutem'] ?? 0);
                    $sectionTotal += $znarzutem;
                    $grandTotal  += $znarzutem;

                    $dr = $priceTable->addRow();
                    $dr->addCell($colW[0])->addText($i + 1,                                          ['size' => 10]);
                    $dr->addCell($colW[1])->addText($row['opis'] ?? '—',                             ['size' => 10]);
                    $dr->addCell($colW[2])->addText($row['ilosc'] ?? '',                             ['size' => 10]);
                    $dr->addCell($colW[3])->addText($row['jedn'] ?? '',                              ['size' => 10]);
                    $dr->addCell($colW[4])->addText(number_format((float)($row['cena_jedn'] ?? 0), 2, ',', ' '), ['size' => 10]);
                    $dr->addCell($colW[5], ['bgColor' => 'F5FAF7'])
                       ->addText(number_format($znarzutem, 2, ',', ' '), ['bold' => true, 'size' => 10]);
                }

                $section->addTextBreak(1);
            }

            // Łącznie netto
            $totalTable = $section->addTable([
                'borderSize'  => 0,
                'borderColor' => 'FFFFFF',
                'cellMargin'  => 100,
                'width'       => $pageW,
                'unit'        => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
            ]);
            $tRow = $totalTable->addRow(Converter::cmToTwip(1.2));
            $tRow->addCell(Converter::cmToTwip(13), ['bgColor' => $green])
                 ->addText('Łącznie netto', ['bold' => true, 'size' => 13, 'color' => 'FFFFFF']);
            $tRow->addCell(Converter::cmToTwip(4), ['bgColor' => $green])
                 ->addText(
                     number_format($grandTotal, 2, ',', ' ') . ' zł',
                     ['bold' => true, 'size' => 13, 'color' => 'FFFFFF'],
                     ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]
                 );

            $section->addTextBreak(1);
        }

        // ── DELEGACJE ─────────────────────────────────────────────────────────
        $delegations = $offer->delegations ?? [];
        if (!empty($delegations)) {
            $addSectionHeader('Delegacje');

            // Szerokości: 4+4+1.5+1.5+1.5+1.5+3 = 17 cm
            $dColW = [
                Converter::cmToTwip(4),
                Converter::cmToTwip(4),
                Converter::cmToTwip(1.5),
                Converter::cmToTwip(1.5),
                Converter::cmToTwip(1.5),
                Converter::cmToTwip(1.5),
                Converter::cmToTwip(3),
            ];

            $dTable = $section->addTable([
                'borderSize'   => 4,
                'borderColor'  => 'E0E0E0',
                'cellMargin'   => 80,
                'width'        => $pageW,
                'unit'         => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
                'columnWidths' => $dColW,
            ]);

            $dh = $dTable->addRow(Converter::cmToTwip(0.8));
            foreach ([
                ['Lokalizacja', $dColW[0]],
                ['Adres',       $dColW[1]],
                ['Km',          $dColW[2]],
                ['Wyjazdy',     $dColW[3]],
                ['Osoby',       $dColW[4]],
                ['Noclegi',     $dColW[5]],
                ['Koszt',       $dColW[6]],
            ] as [$h, $w]) {
                $dh->addCell($w, ['bgColor' => $green])
                   ->addText($h, ['bold' => true, 'size' => 9, 'color' => 'FFFFFF']);
            }

            $delegTotal = 0;
            foreach ($delegations as $del) {
                $km    = (int)($del['km'] ?? 0);
                $wyj   = (int)($del['wyjazdy'] ?? 1);
                $os    = (int)($del['osoby'] ?? 1);
                $noc   = (int)($del['noce'] ?? 0);
                $sKm   = (float)($del['stawka_km'] ?? 1.10);
                $sNoc  = (float)($del['stawka_noc'] ?? 200);
                $koszt = $km * 2 * $wyj * $sKm + $noc * $os * $sNoc;
                $delegTotal += $koszt;

                $dr = $dTable->addRow();
                $dr->addCell($dColW[0])->addText($del['nazwa'] ?? '—',                   ['size' => 9]);
                $dr->addCell($dColW[1])->addText($del['adres'] ?? '—',                   ['size' => 9]);
                $dr->addCell($dColW[2])->addText($km . ' km',                            ['size' => 9]);
                $dr->addCell($dColW[3])->addText((string)$wyj,                           ['size' => 9]);
                $dr->addCell($dColW[4])->addText((string)$os,                            ['size' => 9]);
                $dr->addCell($dColW[5])->addText((string)$noc,                           ['size' => 9]);
                $dr->addCell($dColW[6], ['bgColor' => 'F5FAF7'])
                   ->addText(number_format($koszt, 2, ',', ' ') . ' zł', ['bold' => true, 'size' => 9]);
            }

            $section->addTextBreak(1);
        }

        foreach ($afterPriceSections as $textSection) {
            $addTextSection($textSection);
        }

        // ── TERMIN WAŻNOŚCI ──────────────────────────────────────────────────
        if ($offer->valid_until) {
            $addSectionHeader('Termin ważności oferty');
            $section->addText(
                'Niniejsza oferta ważna jest do dnia ' . \Carbon\Carbon::parse($offer->valid_until)->format('d.m.Y') . '.',
                ['size' => 11],
                ['spaceAfter' => Converter::pointToTwip(10)]
            );
            $section->addTextBreak(1);
        }

        // ── STOPKA ───────────────────────────────────────────────────────────
        $footer = $section->addFooter();
        $footer->addText(
            'Enesa sp. z o. o.  ·  ul. Konarskiego 18C, 44-100 Gliwice  ·  NIP: 6312741198  ·  biuro@enesa.pl',
            ['size' => 8, 'color' => '999999'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $footer->addText(
            'Oferta ważna 30 dni od daty wystawienia. Wszystkie ceny podano w kwotach netto.',
            ['size' => 8, 'color' => '999999', 'italic' => true],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );

        // ── GENEROWANIE ──────────────────────────────────────────────────────
        $filename = 'Oferta_' . str_replace(['/', '\\', ' '], '_', $offer->offer_number ?? $offer->id) . '.docx';
        $tempPath = storage_path('app/temp/' . $filename);

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function updateUnitPrices(Request $request, Offer $offer): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $offer);

        $offer->update(['show_unit_prices' => $request->boolean('show_unit_prices')]);
        return response()->json(['ok' => true]);
    }

    public function saveAsTemplate(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        OfferSavedTemplate::create([
            'name'             => $data['name'],
            'offer_id'         => $offer->id,
            'content_subject'  => $offer->content_subject,
            'content_scope'    => $offer->content_scope,
            'content_deadline' => $offer->content_deadline,
            'content_payment'  => $offer->content_payment,
            'price_sections'   => $offer->price_sections,
            'created_by'       => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Szablon został zapisany jako: ' . $data['name']);
    }

    public function getDistance(Request $request): JsonResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $destination = null;

        if ($request->filled('destination')) {
            $raw = trim($request->input('destination'));
            if (empty($raw)) {
                return response()->json(['error' => 'Podaj adres lub miasto.'], 422);
            }
            $lower = strtolower($raw);
            $destination = (!str_contains($lower, 'polska') && !str_contains($lower, 'poland'))
                ? $raw . ', Polska'
                : $raw;

        } elseif ($request->filled('company_id')) {
            $request->validate(['company_id' => ['required', 'exists:companies,id']]);
            $company     = Company::findOrFail($request->company_id);
            $parts       = array_filter([$company->address, $company->city, 'Polska']);
            $destination = implode(', ', $parts);
            if (empty(trim(str_replace(['Polska', ',', ' '], '', $destination)))) {
                return response()->json(['error' => 'Firma nie ma uzupełnionego adresu.'], 422);
            }
        } else {
            return response()->json(['error' => 'Podaj adres lub ID firmy.'], 422);
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins'      => 'ul. Konarskiego 18C, 44-100 Gliwice, Polska',
            'destinations' => $destination,
            'mode'         => 'driving',
            'language'     => 'pl',
            'key'          => config('services.google.maps_key'),
        ]);

        $json       = $response->json();
        $status     = $json['status'] ?? '';
        $elemStatus = $json['rows'][0]['elements'][0]['status'] ?? '';

        if ($status !== 'OK' || $elemStatus !== 'OK') {
            Log::error('Distance Matrix error', [
                'address_used'   => $destination,
                'status'         => $status,
                'element_status' => $elemStatus,
            ]);
            return response()->json([
                'error' => 'Nie udało się pobrać odległości dla: "' . $destination . '". Sprawdź wpisany adres.',
            ], 422);
        }

        $element = $json['rows'][0]['elements'][0];
        $km      = round($element['distance']['value'] / 1000);
        $minutes = (int) round($element['duration']['value'] / 60);

        return response()->json([
            'km'      => $km,
            'minutes' => $minutes,
            'address' => $destination,
        ]);
    }

    public function destroy(Offer $offer): RedirectResponse
    {
        $this->authorize('delete', $offer);

        $offer->delete();
        return redirect()->route('offers.index')
            ->with('success', 'Oferta ' . $offer->offer_full_number . ' została usunięta.');
    }
    public function aiAssist(Request $request): JsonResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $data = $request->validate([
            'field'        => ['required', 'string', 'max:50'],
            'mode'         => ['required', 'in:improve,generate_table'],
            'current'      => ['nullable', 'string'],
            'offer_title'  => ['nullable', 'string'],
            'company_name' => ['nullable', 'string'],
            'section_name' => ['nullable', 'string', 'max:255'],
            'table_request' => ['nullable', 'string', 'max:1000'],
        ]);

        $fieldLabels = [
            'content_subject'  => 'Przedmiot oferty',
            'content_scope'    => 'Zakres prac',
            'content_deadline' => 'Termin realizacji',
            'content_payment'  => 'Warunki płatności',
        ];

        $label   = $fieldLabels[$data['field']] ?? ($request->input('section_name') ?? 'Sekcja oferty');
        $title   = $data['offer_title'] ?? 'oferta';
        $company = $data['company_name'] ?? 'klient';
        $current = preg_replace('/<br\s*\/?>/i', "\n", $data['current'] ?? '');
        $current = preg_replace('/<\/p>/i', "\n", $current);
        $current = preg_replace('/<\/li>/i', "\n", $current);
        $current = strip_tags($current);
        $current = trim($current);

        if ($data['mode'] === 'generate_table') {
            $tableRequest = trim($data['table_request'] ?? '');
            if ($tableRequest === '') {
                return response()->json(['error' => 'Opisz dane, które mają znaleźć się w tabeli.'], 422);
            }

            $prompt = "Jesteś asystentem przygotowującym profesjonalne oferty handlowe w branży audytów energetycznych i efektywności energetycznej.

Sekcja dokumentu: {$label}
Tytuł oferty: {$title}
Firma klienta: {$company}

Zadanie użytkownika:
{$tableRequest}

Utwórz czytelną, zwartą tabelę HTML do wklejenia do oferty.
- Zwróć wyłącznie jeden element <table> z <thead>, <tbody>, <tr>, <th> i <td>.
- Użyj od 2 do 6 kolumn i od 1 do 12 wierszy danych, zależnie od polecenia.
- Nie wymyślaj danych, cen, terminów ani parametrów. Gdy użytkownik nie podał konkretnej wartości, pozostaw komórkę pustą.
- Nie dodawaj żadnego tekstu przed ani po tabeli, markdownu ani komentarzy.";
        } else {
        $prompt = "Jesteś asystentem pomagającym pisać profesjonalne oferty handlowe w branży audytów energetycznych i efektywności energetycznej.

Sekcja dokumentu: {$label}
Tytuł oferty: {$title}
Firma klienta: {$company}

Tekst wpisany przez użytkownika (może zawierać skróty, literówki, niedokończone zdania):
{$current}

Twoje zadanie:
- Popraw błędy ortograficzne, gramatyczne i literówki (np. 'tygoss' → 'tydzień')
- Uzupełnij skrócone lub urwane zdania zachowując sens oryginału
- Zachowaj WSZYSTKIE informacje, liczby i okresy czasu które podał użytkownik
- NIE dodawaj nowych etapów, dat ani informacji których nie ma w tekście
- NIE zaczynaj od słów 'Przedmiot oferty', 'Zakres prac', 'Termin realizacji', 'Warunki płatności'

Formatowanie HTML:
- Jeśli tekst zawiera harmonogram, etapy lub listę punktów → użyj <ul><li>...</li></ul>
- Jeśli tekst to jeden lub kilka zdań → użyj <p>...</p>
- Dla sekcji 'Warunki płatności' możesz użyć <ul> dla poszczególnych warunków
- Zwróć TYLKO HTML, bez markdown, bez backtików, bez komentarzy";

        }

        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 4096,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Błąd API AI'], 500);
        }

        $content = $response->json('content.0.text') ?? '';
        $content = preg_replace('/^```[\w]*\n?/m', '', $content);
        $content = preg_replace('/```$/m', '', $content);
        $content = preg_replace('/<p>\s*<br\s*\/?>\s*<\/p>/i', '', $content);
        $content = preg_replace('/<p>\s*<\/p>/i', '', $content);
        $content = $this->cleanQuillHtml(trim($content));

        if ($data['mode'] === 'generate_table' && !str_starts_with($content, '<table>')) {
            return response()->json(['error' => 'AI nie zwróciło poprawnej tabeli. Spróbuj opisać dane dokładniej.'], 422);
        }

        return response()->json(['html' => $content]);
    }

    public function clone(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorize('update', $offer);

        $data = $request->validate([
            'mode' => ['required', 'in:offer,template'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ]);

        $isTemplate = $data['mode'] === 'template';

        $newOffer = $offer->replicate();
        $newOffer->offer_number = Offer::generateNumber($isTemplate);
        $newOffer->offer_full_number = $newOffer->offer_number;
        $newOffer->status = 'w_toku';
        $newOffer->is_template = $isTemplate;
        $newOffer->created_by_id = auth()->id();
        $newOffer->kwota_netto = $offer->kwota_netto;
        if ($isTemplate) {
            $newOffer->company_id = null;
        } elseif ($data['company_id'] ?? false) {
            $newOffer->company_id = $data['company_id'];
        }
        $newOffer->save();

        if ($offer->offerDelegation) {
            $newDelegation = $offer->offerDelegation->replicate();
            $newDelegation->offer_id = $newOffer->id;
            $newDelegation->save();
        }

        $route = $isTemplate ? route('offers.edit', $newOffer) : route('offers.edit', $newOffer);
        return redirect($route)->with('success', $isTemplate ? 'Szablon został zapisany.' : 'Nowa oferta została utworzona.');
    }

    private function removePriceData(Offer $offer): void
    {
        $offer->setAttribute('kwota_netto', null);
        $offer->setAttribute('price_sections', null);
        $offer->setAttribute('show_unit_prices', false);
        $offer->setAttribute('delegations', null);
        $offer->setAttribute('content_payment', null);
    }

    private function cleanQuillHtml(?string $html): string
    {
        if (!$html) return '';

        // Zamień listy Quill 2 (data-list="bullet") na zwykłe <ul><li>
        $html = preg_replace_callback(
            '/<ol>(.*?)<\/ol>/s',
            function ($matches) {
                $inner = $matches[1];
                $isOrdered = preg_match('/data-list="ordered"/i', $inner) === 1;
                $inner = preg_replace('/<li[^>]*>/i', '<li>', $inner);
                $inner = preg_replace('/<span[^>]*class="ql-ui"[^>]*>.*?<\/span>/s', '', $inner);
                return ($isOrdered ? '<ol>' : '<ul>') . $inner . ($isOrdered ? '</ol>' : '</ul>');
            },
            $html
        );

        // Usuń wszystkie atrybuty class z tagów
        $html = preg_replace('/\s+class="[^"]*"/', '', $html);

        // Usuń contenteditable
        $html = preg_replace('/\s+contenteditable="[^"]*"/', '', $html);

        // Usuń puste paragrafy
        $html = preg_replace('/<p>\s*<br\s*\/?>\s*<\/p>/i', '', $html);
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html);

        // Usuń pozostałe span ql-ui
        $html = preg_replace('/<span[^>]*ql-ui[^>]*>.*?<\/span>/s', '', $html);

        // Zawartość sekcji trafia potem bezpośrednio do PDF. Pozwalamy tylko na
        // formatowanie obsługiwane przez edytor i szablon oferty, bez atrybutów.
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><table><thead><tbody><tr><th><td>');
        $html = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li|h2|h3|table|thead|tbody|tr|th|td)\\b[^>]*>/i', '<$1>', $html);

        return trim($html);
    }

    private function normalizeTextSections(mixed $sections): ?array
    {
        if (!is_array($sections)) {
            return null;
        }

        $normalized = [];
        foreach ($sections as $index => $section) {
            if (!is_array($section)) {
                continue;
            }

            $name = trim(strip_tags((string) ($section['name'] ?? '')));
            $content = $this->cleanQuillHtml((string) ($section['content'] ?? ''));
            $placement = $section['placement'] ?? ($index < 2 ? 'before_price' : 'after_price');

            $normalized[] = [
                'name' => mb_substr($name !== '' ? $name : 'Sekcja oferty', 0, 120),
                'content' => $content,
                'placement' => in_array($placement, ['before_price', 'after_price'], true)
                    ? $placement
                    : ($index < 2 ? 'before_price' : 'after_price'),
            ];
        }

        return $normalized;
    }
}
