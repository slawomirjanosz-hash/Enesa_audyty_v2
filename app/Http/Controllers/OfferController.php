<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferDelegation;
use App\Models\OfferMessage;
use App\Models\OfferRequest;
use App\Models\OfferTemplateType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $query = Offer::with(['company', 'assignedUser', 'createdBy'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $offers = $query->paginate(20)->withQueryString();

        $stats = [
            'w_toku'        => Offer::where('status', 'w_toku')->count(),
            'wygrana'       => Offer::where('status', 'wygrana')->count(),
            'przegrana'     => Offer::where('status', 'przegrana')->count(),
            'zarchiwizowana'=> Offer::where('status', 'zarchiwizowana')->count(),
        ];

        return view('offers.index', compact('offers', 'stats'));
    }

    public function create(Request $request): View
    {
        $companies           = Company::orderBy('name')->get();
        $users               = User::role(['admin', 'auditor'])->orderBy('name')->get();
        $offerTemplateTypes  = OfferTemplateType::where('is_active', true)
            ->with('offerTemplateVersions')
            ->get();

        $offerRequest = $request->filled('offer_request_id')
            ? OfferRequest::find($request->offer_request_id)
            : null;

        $suggestedNumber = Offer::generateNumber();
        $numberExists    = Offer::where('offer_number', $suggestedNumber)->exists();

        return view('offers.create', compact(
            'companies',
            'users',
            'offerTemplateTypes',
            'offerRequest',
            'suggestedNumber',
            'numberExists',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id'                => ['required', 'exists:companies,id'],
            'offer_number'              => ['required', 'string', 'unique:offers,offer_number'],
            'offer_slug'                => ['nullable', 'string', 'max:255'],
            'status'                    => ['required', 'in:w_toku,wygrana,przegrana,zarchiwizowana'],
            'assigned_user_id'          => ['nullable', 'exists:users,id'],
            'kwota_netto'               => ['nullable', 'numeric', 'min:0'],
            'notes'                     => ['nullable', 'string'],
            'offer_template_version_id' => ['nullable', 'exists:offer_template_versions,id'],
            'offer_request_id'          => ['nullable', 'exists:offer_requests,id'],
            // Delegation
            'km_do_klienta'             => ['nullable', 'integer', 'min:0'],
            'czas_dojazdu_min'          => ['nullable', 'integer', 'min:0'],
            'liczba_wyjazdow'           => ['required', 'integer', 'min:1'],
            'czy_kilkudniowy'           => ['boolean'],
            'liczba_noc'                => ['required', 'integer', 'min:0'],
            'liczba_osob'               => ['required', 'integer', 'min:1'],
            'stawka_noc'                => ['required', 'numeric', 'min:0'],
        ]);

        $slug = $request->filled('offer_slug')
            ? '_' . Str::slug($request->offer_slug, '_')
            : '';

        $offer = Offer::create([
            'company_id'                => $data['company_id'],
            'offer_number'              => $data['offer_number'],
            'offer_slug'                => $data['offer_slug'] ?? null,
            'offer_full_number'         => $data['offer_number'] . $slug,
            'status'                    => $data['status'],
            'assigned_user_id'          => $data['assigned_user_id'] ?? null,
            'created_by_id'             => auth()->id(),
            'kwota_netto'               => $data['kwota_netto'] ?? null,
            'notes'                     => $data['notes'] ?? null,
            'offer_template_version_id' => $data['offer_template_version_id'] ?? null,
            'offer_request_id'          => $data['offer_request_id'] ?? null,
        ]);

        OfferDelegation::create([
            'offer_id'         => $offer->id,
            'km_do_klienta'    => $data['km_do_klienta'] ?? null,
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
        $companies           = Company::orderBy('name')->get();
        $users               = User::role(['admin', 'auditor'])->orderBy('name')->get();
        $offerTemplateTypes  = OfferTemplateType::where('is_active', true)
            ->with('offerTemplateVersions')
            ->get();
        $offerRequest = $offer->offerRequest;

        $suggestedNumber = $offer->offer_number;
        $numberExists    = false;

        return view('offers.edit', compact(
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
            'status'                    => ['required', 'in:w_toku,wygrana,przegrana,zarchiwizowana'],
            'assigned_user_id'          => ['nullable', 'exists:users,id'],
            'kwota_netto'               => ['nullable', 'numeric', 'min:0'],
            'notes'                     => ['nullable', 'string'],
            'offer_template_version_id' => ['nullable', 'exists:offer_template_versions,id'],
            'offer_request_id'          => ['nullable', 'exists:offer_requests,id'],
            // Delegation
            'km_do_klienta'             => ['nullable', 'integer', 'min:0'],
            'czas_dojazdu_min'          => ['nullable', 'integer', 'min:0'],
            'liczba_wyjazdow'           => ['required', 'integer', 'min:1'],
            'czy_kilkudniowy'           => ['boolean'],
            'liczba_noc'                => ['required', 'integer', 'min:0'],
            'liczba_osob'               => ['required', 'integer', 'min:1'],
            'stawka_noc'                => ['required', 'numeric', 'min:0'],
        ]);

        $slug = $request->filled('offer_slug')
            ? '_' . Str::slug($request->offer_slug, '_')
            : '';

        $offer->update([
            'company_id'                => $data['company_id'],
            'offer_number'              => $data['offer_number'],
            'offer_slug'                => $data['offer_slug'] ?? null,
            'offer_full_number'         => $data['offer_number'] . $slug,
            'status'                    => $data['status'],
            'assigned_user_id'          => $data['assigned_user_id'] ?? null,
            'kwota_netto'               => $data['kwota_netto'] ?? null,
            'notes'                     => $data['notes'] ?? null,
            'offer_template_version_id' => $data['offer_template_version_id'] ?? null,
            'offer_request_id'          => $data['offer_request_id'] ?? null,
        ]);

        $delegation = $offer->offerDelegation ?? new OfferDelegation(['offer_id' => $offer->id]);
        $delegation->fill([
            'km_do_klienta'    => $data['km_do_klienta'] ?? null,
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
        $km      = round($element['distance']['value'] / 1000, 1);
        $minutes = (int) round($element['duration']['value'] / 60);

        return response()->json([
            'km'      => $km,
            'minutes' => $minutes,
            'address' => $destination,
        ]);
    }
}
