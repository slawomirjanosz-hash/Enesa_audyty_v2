<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferDelegation;
use App\Models\OfferMessage;
use App\Models\OfferRequest;
use App\Models\OfferSavedTemplate;
use App\Models\OfferTemplateType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $isTemplate = $request->boolean('template');
        $query = Offer::with(['company', 'assignedUser', 'createdBy'])
            ->where('is_template', $isTemplate)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $offers = $query->paginate(20)->withQueryString();

        $stats = [
            'w_toku'        => Offer::where('status', 'w_toku')->where('is_template', $isTemplate)->count(),
            'wygrana'       => Offer::where('status', 'wygrana')->where('is_template', $isTemplate)->count(),
            'przegrana'     => Offer::where('status', 'przegrana')->where('is_template', $isTemplate)->count(),
            'zarchiwizowana'=> Offer::where('status', 'zarchiwizowana')->where('is_template', $isTemplate)->count(),
        ];

        return view('offers.index', compact('offers', 'stats'));
    }

    public function create(Request $request): View
    {
        $companySettings     = \App\Models\CompanySettings::first();
        $companies           = Company::orderBy('name')->get();
        $users               = User::role(['superadmin', 'admin', 'auditor_senior', 'auditor'])->orderBy('name')->get();
        $offerTemplateTypes  = OfferTemplateType::where('is_active', true)
            ->with('offerTemplateVersions')
            ->get();

        $offerRequest = $request->filled('offer_request_id')
            ? OfferRequest::find($request->offer_request_id)
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
            'offerTemplates',
            'suggestedNumber',
            'numberExists',
        ));
    }

    public function getTemplate(Offer $offer): JsonResponse
    {
        return response()->json([
            'offer_title'      => $offer->offer_title,
            'content_subject'  => $offer->content_subject,
            'content_scope'    => $offer->content_scope,
            'content_deadline' => $offer->content_deadline,
            'content_payment'  => $offer->content_payment,
            'price_sections'   => $offer->price_sections,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id'                => ['required', 'exists:companies,id'],
            'offer_number'              => ['required', 'string', 'unique:offers,offer_number'],
            'offer_slug'                => ['nullable', 'string', 'max:255'],
            'offer_title'               => ['nullable', 'string', 'max:500'],
            'status'                    => ['required', 'in:w_toku,wygrana,przegrana,zarchiwizowana'],
            'assigned_user_id'          => ['nullable', 'exists:users,id'],
            'kwota_netto'               => ['nullable', 'numeric', 'min:0'],
            'valid_until'               => ['nullable', 'date'],
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
        ]);

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
        $offer->load([
            'company',
            'assignedUser',
            'createdBy',
            'offerTemplateVersion',
            'offerRequest',
            'offerDelegation',
            'offerMessages.user',
        ]);

        return view('offers.show', compact('offer'));
    }

    public function edit(Offer $offer): View
    {
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
        $data = $request->validate([
            'company_id'                => ['required', 'exists:companies,id'],
            'offer_number'              => ['required', 'string', 'unique:offers,offer_number,' . $offer->id],
            'offer_slug'                => ['nullable', 'string', 'max:255'],
            'offer_title'               => ['nullable', 'string', 'max:500'],
            'status'                    => ['required', 'in:w_toku,wygrana,przegrana,zarchiwizowana'],
            'assigned_user_id'          => ['nullable', 'exists:users,id'],
            'kwota_netto'               => ['nullable', 'numeric', 'min:0'],
            'valid_until'               => ['nullable', 'date'],
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

        $offer->update([
            'company_id'                => $data['company_id'],
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
        ]);

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
        $data = $request->validate([
            'status' => ['required', 'in:w_toku,wygrana,przegrana,zarchiwizowana'],
            'won_as' => ['nullable', 'in:audyt,projekt,inne'],
        ]);

        $offer->update($data);

        return redirect()->back()->with('success', 'Status oferty został zaktualizowany.');
    }

    public function storeMessage(Request $request, Offer $offer): RedirectResponse
    {
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
        $offer->load(['company', 'assignedUser', 'offerDelegation']);
        $companySettings = \App\Models\CompanySettings::first();

        $offer->content_subject  = $this->cleanQuillHtml($offer->content_subject);
        $offer->content_scope    = $this->cleanQuillHtml($offer->content_scope);
        $offer->content_deadline = $this->cleanQuillHtml($offer->content_deadline);
        $offer->content_payment  = $this->cleanQuillHtml($offer->content_payment);

        // Allow toggle state to be passed via ?unit= query param (from edit page PDF button)
        if ($request->has('unit')) {
            $offer->show_unit_prices = $request->boolean('unit');
        }

        // Logo: always generate fresh base64 from PNG to avoid encoding issues
        $logoBase64 = null;
        $logoPath = public_path('Logo2.png');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        } else {
            \Illuminate\Support\Facades\Log::error('PDF logo: nie znaleziono public/Logo2.png pod ' . $logoPath);
        }

        $html = view('offers.pdf', compact('offer', 'companySettings', 'logoBase64'))->render();

        \Illuminate\Support\Facades\Log::info('PDF generation environment', [
            'memory_limit' => ini_get('memory_limit'),
            'gd_loaded' => extension_loaded('gd'),
            'php_version' => PHP_VERSION,
            'logoBase64_length' => $logoBase64 ? strlen($logoBase64) : 0,
        ]);

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 20,
                'margin_bottom' => 20,
                'margin_left' => 15,
                'margin_right' => 15,
                'setAutoTopMargin' => false,
                'setAutoBottomMargin' => false,
            ]);

            $mpdf->WriteHTML($html);

            $filename = 'oferta-' . $offer->fullNumber() . '.pdf';

            return response($mpdf->Output($filename, 'S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('mPDF generation failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    public function updateUnitPrices(Request $request, Offer $offer): \Illuminate\Http\JsonResponse
    {
        $offer->update(['show_unit_prices' => $request->boolean('show_unit_prices')]);
        return response()->json(['ok' => true]);
    }

    public function saveAsTemplate(Request $request, Offer $offer): RedirectResponse
    {
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
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = Company::findOrFail($data['company_id']);

        $parts       = array_filter([$company->address, $company->city, 'Polska']);
        $destination = implode(', ', $parts);

        if (empty(trim(str_replace(['Polska', ',', ' '], '', $destination)))) {
            return response()->json(['error' => 'Firma nie ma uzupełnionego adresu.'], 422);
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins'      => 'ul. Konarskiego 18C, 44-100 Gliwice, Polska',
            'destinations' => $destination,
            'mode'         => 'driving',
            'language'     => 'pl',
            'key'          => config('services.google.maps_key'),
        ]);

        $json   = $response->json();
        $status = $json['status'] ?? '';
        $elemStatus = $json['rows'][0]['elements'][0]['status'] ?? '';

        if ($status !== 'OK' || $elemStatus !== 'OK') {
            Log::error('Distance Matrix error', [
                'response_body' => $json,
                'address_used' => $destination,
                'status' => $status,
                'element_status' => $elemStatus,
            ]);
            return response()->json([
                'debug' => true,
                'status' => $status,
                'element_status' => $elemStatus,
                'full_response' => $json,
                'address_used' => $destination,
                'api_key_first_chars' => substr(config('services.google.maps_key'), 0, 10) . '...',
            ]);
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
        $offer->delete();
        return redirect()->route('offers.index')
            ->with('success', 'Oferta ' . $offer->offer_full_number . ' została usunięta.');
    }
    public function aiAssist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field'        => ['required', 'in:content_subject,content_scope,content_deadline,content_payment'],
            'mode'         => ['required', 'in:improve'],
            'current'      => ['nullable', 'string'],
            'offer_title'  => ['nullable', 'string'],
            'company_name' => ['nullable', 'string'],
        ]);

        $fieldLabels = [
            'content_subject'  => 'Przedmiot oferty',
            'content_scope'    => 'Zakres prac',
            'content_deadline' => 'Termin realizacji',
            'content_payment'  => 'Warunki płatności',
        ];

        $label   = $fieldLabels[$data['field']];
        $title   = $data['offer_title'] ?? 'oferta';
        $company = $data['company_name'] ?? 'klient';
        $current = preg_replace('/<br\s*\/?>/i', "\n", $data['current'] ?? '');
        $current = preg_replace('/<\/p>/i', "\n", $current);
        $current = preg_replace('/<\/li>/i', "\n", $current);
        $current = strip_tags($current);
        $current = trim($current);

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

        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1024,
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
        $content = trim($content);

        return response()->json(['html' => $content]);
    }

    public function clone(Request $request, Offer $offer): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:offer,template'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ]);

        $isTemplate = $data['mode'] === 'template';

        $newOffer = $offer->replicate();
        $newOffer->offer_number = Offer::generateNumber();
        $newOffer->offer_full_number = $newOffer->offer_number;
        $newOffer->status = 'w_toku';
        $newOffer->is_template = $isTemplate;
        $newOffer->created_by_id = auth()->id();
        $newOffer->kwota_netto = $offer->kwota_netto;
        if ($data['company_id'] ?? false) {
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

    private function cleanQuillHtml(?string $html): string
    {
        if (!$html) return '';

        // Zamień listy Quill 2 (data-list="bullet") na zwykłe <ul><li>
        $html = preg_replace_callback(
            '/<ol>(.*?)<\/ol>/s',
            function ($matches) {
                $inner = $matches[1];
                $inner = preg_replace('/<li[^>]*data-list="bullet"[^>]*>/i', '<li>', $inner);
                $inner = preg_replace('/<li[^>]*data-list="ordered"[^>]*>/i', '<li>', $inner);
                $inner = preg_replace('/<span[^>]*class="ql-ui"[^>]*>.*?<\/span>/s', '', $inner);
                return '<ul>' . $inner . '</ul>';
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

        return trim($html);
    }
}
