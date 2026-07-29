@extends('layouts.app')

@section('page-title', 'Zapytanie od klienta')

@section('content')

<div style="max-width:760px;margin:0 auto;">

    {{-- Nagłówek --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <div>
            <a href="javascript:history.back()" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#1A4D3A;text-decoration:none;font-weight:600;margin-bottom:8px;">
                <i class="ti ti-arrow-left"></i> Wróć
            </a>
            <h1 style="font-family:'Manrope',sans-serif;font-size:20px;font-weight:700;color:#1A1A1A;margin:0;">
                Zapytanie: {{ $offerRequest->offerFormTemplate?->name ?? 'Zapytanie ogólne' }}
            </h1>
            <div style="font-size:13px;color:#888;margin-top:4px;">
                {{ $offerRequest->company?->name ?? '—' }}
                · {{ $offerRequest->created_at->format('d.m.Y H:i') }}
                @if($offerRequest->createdBy)
                    · złożone przez {{ $offerRequest->createdBy->name }}
                @endif
            </div>
        </div>
        <div>
            @php
                $statusColors = [
                    'nowe'      => ['bg' => '#DBEAFE', 'text' => '#1D4ED8'],
                    'w_toku'    => ['bg' => '#FEF3C7', 'text' => '#92400E'],
                    'zamknięte' => ['bg' => '#F3F4F6', 'text' => '#4B5563'],
                ];
                $sc = $statusColors[$offerRequest->status] ?? ['bg' => '#F3F4F6', 'text' => '#4B5563'];
            @endphp
            <span style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;font-family:'Manrope',sans-serif;">
                {{ ucfirst(str_replace('_', ' ', $offerRequest->status)) }}
            </span>
            <div style="margin-top:8px;display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                @if(auth()->user()->hasAnyRole(['superadmin', 'admin', 'auditor_senior']))
                <a href="{{ route('offers.create', ['offer_request_id' => $offerRequest->id]) }}"
                   style="display:inline-flex;align-items:center;gap:6px;background:#92400E;color:#fff;border-radius:7px;padding:7px 14px;font-size:12px;font-weight:700;font-family:'Manrope',sans-serif;text-decoration:none;">
                    <i class="ti ti-calculator"></i> Utwórz ofertę
                </a>
                @endif
                <a href="{{ route('offer-requests.edit', $offerRequest) }}" data-tooltip="Uzupełnij ankietę"
                   style="display:inline-flex;align-items:center;gap:6px;background:#1A4D3A;color:#fff;border-radius:7px;padding:7px 14px;font-size:12px;font-weight:700;font-family:'Manrope',sans-serif;text-decoration:none;">
                    <i class="ti ti-pencil"></i> Edytuj zapytanie
                </a>
                @if($offerRequest->offerFormTemplate)
                <a href="{{ route('offer-requests.pdf', $offerRequest) }}" target="_blank" data-tooltip="Pobierz ankietę w PDF"
                   style="display:inline-flex;align-items:center;gap:6px;background:#fff;color:#1A4D3A;border:1px solid #94C4B0;border-radius:7px;padding:7px 14px;font-size:12px;font-weight:700;font-family:'Manrope',sans-serif;text-decoration:none;">
                    <i class="ti ti-file-type-pdf"></i> PDF
                </a>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:11px 16px;margin-bottom:14px;font-size:13px;display:flex;align-items:center;gap:10px;">
            <i class="ti ti-circle-check"></i> {{ session('success') }}
        </div>
    @endif

    {{-- Odpowiedzi formularza --}}
    <div style="background:#fff;border:1px solid #E5E1D8;border-radius:12px;padding:22px;margin-bottom:16px;">
        <div style="font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;color:#1A4D3A;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
            <i class="ti ti-clipboard-list"></i> Odpowiedzi
        </div>

        @php
            $sections = $offerRequest->offerFormTemplate?->sectionedFields() ?? [];
            $responses = $offerRequest->form_responses ?? [];
            $usedKeys = [];
        @endphp

        @if(empty($responses))
            <p style="color:#888;font-size:13px;">Brak odpowiedzi.</p>
        @else
            @foreach($sections as $section)
                @php
                    $answered = collect($section['fields'])->filter(function ($f) use ($responses) {
                        return isset($f['key'])
                            && array_key_exists($f['key'], $responses)
                            && $responses[$f['key']] !== null
                            && $responses[$f['key']] !== '';
                    });
                @endphp

                @if($answered->isNotEmpty())
                    @if(!empty($section['title']))
                        <div style="font-family:'Manrope',sans-serif;font-size:12px;font-weight:800;color:#1A4D3A;text-transform:uppercase;letter-spacing:.06em;margin:22px 0 12px;padding-bottom:6px;border-bottom:1px solid #E5E1D8;">
                            {{ $section['title'] }}
                        </div>
                    @endif

                    @foreach($answered as $f)
                        @php $usedKeys[] = $f['key']; @endphp
                        <div style="margin-bottom:14px;">
                            <div style="font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">
                                {{ $f['label'] ?: $f['key'] }}
                            </div>
                            <div style="background:#FAFAF6;border:1px solid #E5E1D8;border-radius:7px;padding:10px 12px;font-size:13px;color:#1A1A1A;word-break:break-word;">
                                {{ \App\Models\OfferFormTemplate::displayValue($responses[$f['key']]) }}
                            </div>
                        </div>
                    @endforeach
                @endif
            @endforeach

            @php $orphans = collect($responses)->except($usedKeys)->filter(fn ($v) => $v !== null && $v !== ''); @endphp
            @if($orphans->isNotEmpty())
                <div style="font-family:'Manrope',sans-serif;font-size:12px;font-weight:800;color:#92400E;text-transform:uppercase;letter-spacing:.06em;margin:22px 0 12px;padding-bottom:6px;border-bottom:1px solid #E5E1D8;">
                    Pozostałe odpowiedzi
                </div>
                @foreach($orphans as $key => $value)
                    <div style="margin-bottom:14px;">
                        <div style="font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">{{ $key }}</div>
                        <div style="background:#FAFAF6;border:1px solid #E5E1D8;border-radius:7px;padding:10px 12px;font-size:13px;color:#1A1A1A;word-break:break-word;">
                            {{ is_array($value) ? implode(', ', $value) : $value }}
                        </div>
                    </div>
                @endforeach
            @endif
        @endif
    </div>

    {{-- Klient końcowy / link do ankiety --}}
    <div id="klient-koncowy" style="background:#fff;border:1px solid #E5E1D8;border-radius:12px;padding:18px 20px;margin-bottom:16px;">
        <div style="font-family:'Manrope',sans-serif;font-weight:700;font-size:14px;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
            <i class="ti ti-user-share" style="color:#1A4D3A;"></i> Klient końcowy — ankieta do wypełnienia
        </div>

        <form method="POST" action="{{ route('offer-requests.save-public', $offerRequest) }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="font-size:11px;font-weight:700;color:#555;">Osoba (klient końcowy)</label>
                    <input type="text" name="end_client_name" value="{{ old('end_client_name', $offerRequest->end_client_name) }}" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:700;color:#555;">Firma klienta końcowego</label>
                    <input type="text" name="end_client_company" value="{{ old('end_client_company', $offerRequest->end_client_company) }}" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:700;color:#555;">E-mail</label>
                    <input type="email" name="end_client_email" value="{{ old('end_client_email', $offerRequest->end_client_email) }}" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:700;color:#555;">Telefon</label>
                    <input type="text" name="end_client_phone" value="{{ old('end_client_phone', $offerRequest->end_client_phone) }}" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
                </div>
            </div>
            <button type="submit" style="margin-top:12px;background:#1A4D3A;color:#F5F0E8;border:none;border-radius:8px;padding:9px 18px;font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;cursor:pointer;">
                @if($offerRequest->publicUrl())
                    <i class="ti ti-device-floppy"></i> Zapisz dane klienta
                @else
                    <i class="ti ti-link"></i> Zapisz i wygeneruj link
                @endif
            </button>
        </form>

        @if($offerRequest->publicUrl())
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F0EDE6;">
                <label style="font-size:11px;font-weight:700;color:#555;">Link do wysłania klientowi końcowemu:</label>
                <div style="display:flex;gap:8px;margin-top:6px;">
                    <input type="text" id="public-link" readonly value="{{ $offerRequest->publicUrl() }}" style="flex:1;background:#F0F7F3;border:1px solid #94C4B0;border-radius:7px;padding:8px 10px;font-size:13px;color:#1A4D3A;box-sizing:border-box;">
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('public-link').value); this.textContent='Skopiowano';" style="background:#fff;border:1px solid #D0CCC0;border-radius:7px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">Kopiuj</button>
                </div>
                @if($offerRequest->public_filled_at)
                    <div style="margin-top:8px;font-size:12px;color:#166534;font-weight:600;"><i class="ti ti-circle-check"></i> Ankieta wypełniona przez klienta końcowego {{ $offerRequest->public_filled_at->format('d.m.Y H:i') }}</div>
                @else
                    <div style="margin-top:8px;font-size:12px;color:#888;">Wyślij ten link klientowi końcowemu ze swojej skrzynki. Odpowiedzi pojawią się tu automatycznie.</div>
                @endif
            </div>
        @endif
    </div>

    {{-- Zmiana statusu --}}
    <div style="background:#fff;border:1px solid #E5E1D8;border-radius:12px;padding:22px;">
        <div style="font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;color:#1A4D3A;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
            <i class="ti ti-settings"></i> Zmień status
        </div>
        <form method="POST" action="{{ route('offer-requests.update-status', $offerRequest) }}" style="display:flex;gap:10px;align-items:center;">
            @csrf @method('PATCH')
            <select name="status" style="background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 12px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                <option value="nowe"      {{ $offerRequest->status === 'nowe'      ? 'selected' : '' }}>Nowe</option>
                <option value="w_toku"    {{ $offerRequest->status === 'w_toku'    ? 'selected' : '' }}>W toku</option>
                <option value="zamknięte" {{ $offerRequest->status === 'zamknięte' ? 'selected' : '' }}>Zamknięte</option>
            </select>
            <button type="submit" style="display:inline-flex;align-items:center;gap:6px;background:#1A4D3A;color:#F5F0E8;border:none;border-radius:8px;padding:8px 16px;font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;cursor:pointer;">
                <i class="ti ti-device-floppy"></i> Zapisz
            </button>
        </form>
    </div>

</div>
@endsection
