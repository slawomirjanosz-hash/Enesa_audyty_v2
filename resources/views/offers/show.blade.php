@extends('layouts.app')

@section('page-title', $offer->fullNumber())

@push('styles')
<style>
    /* ── Layout ─────────────────────────────────────── */
    .show-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 20px;
        align-items: start;
    }

    /* ── Card ───────────────────────────────────────── */
    .show-card {
        background: #fff;
        border: 1px solid #E5E1D8;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
    }
    .show-card:last-child { margin-bottom: 0; }
    .show-card-header {
        padding: 14px 22px;
        border-bottom: 1px solid #F0EDE6;
        background: #FAFAF6;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .show-card-header i { font-size: 17px; color: var(--green); }
    .show-card-title {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        color: #1A1A1A;
    }
    .show-card-body { padding: 20px 22px; }

    /* ── Meta grid ──────────────────────────────────── */
    .meta-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 20px;
    }
    .meta-item-label {
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 3px;
    }
    .meta-item-value {
        font-family: 'Lato', sans-serif;
        font-size: 14px;
        color: #1A1A1A;
    }

    /* ── Badges ─────────────────────────────────────── */
    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        font-family: 'Manrope', sans-serif;
        white-space: nowrap;
    }
    .badge-blue   { background: #DBEAFE; color: #1D4ED8; }
    .badge-green  { background: #DCFCE7; color: #166534; }
    .badge-red    { background: #FEE2E2; color: #B91C1C; }
    .badge-gray   { background: #F3F4F6; color: #4B5563; }
    .badge-orange { background: #FEF3C7; color: #92400E; }

    /* ── Delegation table ───────────────────────────── */
    .deleg-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .deleg-table td { padding: 8px 4px; border-bottom: 1px solid #F0EDE6; }
    .deleg-table tr:last-child td { border-bottom: none; }
    .deleg-table td:first-child { color: #666; font-family: 'Manrope', sans-serif; font-weight: 600; width: 55%; }
    .deleg-table td:last-child  { color: #1A1A1A; font-family: 'Lato', sans-serif; font-weight: 700; text-align: right; }
    .deleg-total { background: #F0F7F3; border-radius: 8px; padding: 12px 14px; margin-top: 14px; display: flex; justify-content: space-between; align-items: center; }
    .deleg-total-label { font-family: 'Manrope', sans-serif; font-size: 11px; font-weight: 700; color: var(--green); text-transform: uppercase; letter-spacing: .05em; }
    .deleg-total-value { font-family: 'Lato', sans-serif; font-size: 22px; font-weight: 900; color: var(--green); }

    /* ── Messages ───────────────────────────────────── */
    .message-item {
        padding: 12px 0;
        border-bottom: 1px solid #F0EDE6;
    }
    .message-item:last-child { border-bottom: none; }
    .message-avatar {
        width: 32px; height: 32px;
        border-radius: 50%;
        background: var(--green);
        color: #fff;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .message-body {
        font-family: 'Lato', sans-serif;
        font-size: 14px;
        color: #1A1A1A;
        line-height: 1.6;
        white-space: pre-wrap;
        margin-top: 6px;
        padding: 10px 14px;
        background: #FAFAF6;
        border-radius: 0 8px 8px 8px;
        border: 1px solid #F0EDE6;
    }

    /* ── Form fields ────────────────────────────────── */
    .field-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #3a3a3a;
        margin-bottom: 5px;
        font-family: 'Manrope', sans-serif;
    }
    .field-input {
        width: 100%;
        background: #FAFAF6;
        border: 1px solid #D0CCC0;
        border-radius: 7px;
        padding: 9px 12px;
        font-size: 14px;
        font-family: 'Lato', sans-serif;
        color: #1A1A1A;
        outline: none;
        transition: border-color .15s;
        box-sizing: border-box;
    }
    .field-input:focus { border-color: var(--green); background: #fff; }

    /* ── Buttons ────────────────────────────────────── */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: var(--green);
        color: #F5F0E8;
        border: none;
        border-radius: 8px;
        padding: 9px 16px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-primary:hover { background: #143d2d; color: #F5F0E8; }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #fff;
        color: #333;
        border: 1px solid #D0CCC0;
        border-radius: 8px;
        padding: 8px 14px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-secondary:hover { background: #F4F1EA; }

    @media (max-width: 900px) {
        .show-layout { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')

@php
    $d = $offer->offerDelegation;
    $badgeClass = match($offer->status) {
        'w_toku'         => 'badge-blue',
        'wygrana'        => 'badge-green',
        'przegrana'      => 'badge-red',
        'zarchiwizowana' => 'badge-gray',
        default          => 'badge-gray',
    };
    $badgeLabel = match($offer->status) {
        'w_toku'         => 'W toku',
        'wygrana'        => 'Wygrana',
        'przegrana'      => 'Przegrana',
        'zarchiwizowana' => 'Zarchiwizowana',
        default          => $offer->status,
    };
@endphp

{{-- Nagłówek --}}
<div style="margin-bottom:20px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <a href="{{ route('offers.index') }}" style="font-size:13px;color:#5a6a60;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
            <i class="ti ti-arrow-left"></i> Powrót do listy
        </a>
        <div style="margin-top:6px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h1 style="font-family:'Manrope',sans-serif;font-size:20px;font-weight:700;color:var(--green);margin:0;">
                {{ $offer->fullNumber() }}
            </h1>
            <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
        </div>
        <div style="font-family:'Lato',sans-serif;font-size:14px;color:#666;margin-top:4px;">
            {{ $offer->company?->name ?? '—' }}
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" onclick="openPdfModal('{{ route('offers.pdf', $offer) }}', 'Oferta {{ $offer->fullNumber() }}')" class="btn-primary">
            <i class="ti ti-file-type-pdf"></i> Podgląd PDF
        </button>
        <form method="POST" action="{{ route('offers.save-to-storage', $offer) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn-primary" style="background:#2563EB;">
                <i class="ti ti-device-floppy"></i> Zapisz na dysku
            </button>
        </form>
        <a href="{{ route('offers.download-word', $offer) }}"
           style="display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--green);border:1px solid #94C4B0;border-radius:7px;padding:7px 14px;font-size:12px;font-family:'Manrope',sans-serif;font-weight:600;text-decoration:none;">
            <i class="ti ti-file-type-doc"></i> Pobierz Word
        </a>
        <a href="{{ route('offers.edit', $offer) }}" class="btn-primary">
            <i class="ti ti-pencil"></i> Edytuj
        </a>
    </div>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif

<div class="show-layout">
{{-- ═══ MAIN COLUMN ═══ --}}
<div>

{{-- Podstawowe dane --}}
<div class="show-card">
    <div class="show-card-header">
        <i class="ti ti-file-invoice"></i>
        <span class="show-card-title">Podstawowe dane</span>
    </div>
    <div class="show-card-body">
        <div class="meta-grid">
            <div>
                <div class="meta-item-label">Firma klienta</div>
                <div class="meta-item-value">{{ $offer->company?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="meta-item-label">Osoba prowadząca (ENESA)</div>
                <div class="meta-item-value">{{ $offer->assignedUser?->name ?? '— nieprzypisana —' }}</div>
            </div>
            <div>
                <div class="meta-item-label">Kwota netto</div>
                <div class="meta-item-value">
                    @if($offer->kwota_netto && $offer->kwota_netto > 0)
                        <strong>{{ number_format($offer->kwota_netto, 2, ',', ' ') }} zł</strong>
                    @else
                        <em style="color:#aaa;">— brak —</em>
                    @endif
                </div>
            </div>
            <div>
                <div class="meta-item-label">Szablon oferty</div>
                <div class="meta-item-value">
                    {{ $offer->offerTemplateVersion?->offerTemplateType?->name ?? '— bez szablonu —' }}
                    @if($offer->offerTemplateVersion)
                        <span style="color:#888;font-size:12px;">(v.{{ $offer->offerTemplateVersion->version_number }})</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="meta-item-label">Utworzona przez</div>
                <div class="meta-item-value">{{ $offer->createdBy?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="meta-item-label">Data utworzenia</div>
                <div class="meta-item-value">{{ $offer->created_at->format('d.m.Y') }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Delegacja --}}
<div class="show-card">
    <div class="show-card-header">
        <i class="ti ti-car"></i>
        <span class="show-card-title">Delegacja</span>
    </div>
    <div class="show-card-body">
        @if($d)
            <table class="deleg-table">
                <tr>
                    <td>Odległość do klienta</td>
                    <td>{{ $d->km_do_klienta ?? 0 }} km</td>
                </tr>
                <tr>
                    <td>Szacowany czas dojazdu</td>
                    <td>{{ $d->czas_dojazdu_min ?? 0 }} min</td>
                </tr>
                <tr>
                    <td>Liczba wyjazdów</td>
                    <td>{{ $d->liczba_wyjazdow }}</td>
                </tr>
                <tr>
                    <td>Wyjazd wielodniowy</td>
                    <td>
                        @if($d->czy_kilkudniowy)
                            <span class="badge badge-orange">Tak</span>
                        @else
                            <span style="color:#888;">Nie</span>
                        @endif
                    </td>
                </tr>
                @if($d->czy_kilkudniowy)
                <tr>
                    <td>Liczba nocy</td>
                    <td>{{ $d->liczba_noc }}</td>
                </tr>
                <tr>
                    <td>Liczba osób</td>
                    <td>{{ $d->liczba_osob }}</td>
                </tr>
                <tr>
                    <td>Stawka za dobę</td>
                    <td>{{ number_format($d->stawka_noc, 2, ',', ' ') }} zł</td>
                </tr>
                @endif
            </table>
            <div class="deleg-total">
                <span class="deleg-total-label">Szacowany koszt delegacji</span>
                <span class="deleg-total-value">{{ number_format($d->kosztDelegacji(), 2, ',', ' ') }} zł</span>
            </div>
        @else
            <p style="color:#888;font-size:13px;margin:0;">Brak danych delegacji.</p>
        @endif
    </div>
</div>

{{-- Notatki --}}
<div class="show-card">
    <div class="show-card-header">
        <i class="ti ti-notes"></i>
        <span class="show-card-title">Notatki</span>
    </div>
    <div class="show-card-body">
        @if($offer->notes)
            <p style="font-family:'Lato',sans-serif;font-size:14px;color:#1A1A1A;white-space:pre-wrap;margin:0;line-height:1.7;">{{ $offer->notes }}</p>
        @else
            <p style="color:#aaa;font-size:13px;margin:0;font-style:italic;">Brak notatek.</p>
        @endif
    </div>
</div>

{{-- Wiadomości --}}
<div class="show-card" id="messages">
    <div class="show-card-header">
        <i class="ti ti-messages"></i>
        <span class="show-card-title">Wiadomości ({{ $offer->offerMessages->count() }})</span>
    </div>
    <div class="show-card-body">

        @forelse($offer->offerMessages as $msg)
            <div class="message-item">
                <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="message-avatar"><x-user-avatar :user="$msg->user" /></div>
                        <div>
                            <div style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#1A1A1A;">
                                {{ $msg->user?->name ?? 'Nieznany użytkownik' }}
                            </div>
                            <div style="font-size:11px;color:#999;">
                                {{ $msg->created_at->format('d.m.Y H:i') }}
                            </div>
                        </div>
                    </div>
                    @if($msg->is_internal)
                        <span class="badge badge-orange" style="font-size:10px;">Wewnętrzna</span>
                    @endif
                </div>
                <div class="message-body">{{ $msg->tresc }}</div>
            </div>
        @empty
            <p style="color:#aaa;font-size:13px;margin:0;font-style:italic;">Brak wiadomości.</p>
        @endforelse

        {{-- Formularz nowej wiadomości --}}
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid #F0EDE6;">
            <div style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#1A1A1A;margin-bottom:12px;">
                Dodaj wiadomość
            </div>
            <form method="POST" action="{{ route('offers.messages.store', $offer) }}">
                @csrf
                <div style="margin-bottom:10px;">
                    <textarea name="tresc" class="field-input" rows="3"
                              placeholder="Treść wiadomości..."
                              required>{{ old('tresc') }}</textarea>
                    @error('tresc')<div style="font-size:11px;color:#B91C1C;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-family:'Manrope',sans-serif;color:#555;">
                        <input type="checkbox" name="is_internal" value="1"
                               {{ old('is_internal') ? 'checked' : '' }}
                               style="width:15px;height:15px;accent-color:var(--green);">
                        Notatka wewnętrzna (widoczna tylko dla ENESA)
                    </label>
                    <button type="submit" class="btn-primary">
                        <i class="ti ti-send"></i> Wyślij
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

</div>{{-- /main column --}}

{{-- ═══ SIDEBAR ═══ --}}
<div>

{{-- Zmiana statusu --}}
<div class="show-card">
    <div class="show-card-header">
        <i class="ti ti-refresh"></i>
        <span class="show-card-title">Zmień status</span>
    </div>
    <div class="show-card-body">
        <form method="POST" action="{{ route('offers.status', $offer) }}" id="statusForm">
            @csrf
            @method('PATCH')
            <div style="margin-bottom:12px;">
                <label class="field-label" for="status">Status</label>
                <select name="status" id="statusSelect" class="field-input" onchange="toggleWonAs(this)">
                    <option value="w_toku"         {{ $offer->status === 'w_toku'         ? 'selected' : '' }}>W toku</option>
                    <option value="wygrana"        {{ $offer->status === 'wygrana'        ? 'selected' : '' }}>Wygrana</option>
                    <option value="przegrana"      {{ $offer->status === 'przegrana'      ? 'selected' : '' }}>Przegrana</option>
                    <option value="zarchiwizowana" {{ $offer->status === 'zarchiwizowana' ? 'selected' : '' }}>Zarchiwizowana</option>
                </select>
            </div>
            <div id="wonAsSection" style="margin-bottom:12px;{{ $offer->status === 'wygrana' ? '' : 'display:none;' }}">
                <label class="field-label" for="won_as">Typ wygranej</label>
                <select name="won_as" id="won_as" class="field-input">
                    <option value="">— wybierz —</option>
                    <option value="audyt"   {{ $offer->won_as === 'audyt'   ? 'selected' : '' }}>Audyt energetyczny</option>
                    <option value="projekt" {{ $offer->won_as === 'projekt' ? 'selected' : '' }}>Projekt</option>
                    <option value="inne"    {{ $offer->won_as === 'inne'    ? 'selected' : '' }}>Inne</option>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">
                <i class="ti ti-check"></i> Zmień status
            </button>
        </form>
    </div>
</div>

{{-- Powiązane zapytanie --}}
@if($offer->offerRequest)
<div class="show-card">
    <div class="show-card-header">
        <i class="ti ti-link"></i>
        <span class="show-card-title">Powiązane zapytanie</span>
    </div>
    <div class="show-card-body">
        <div style="font-size:13px;color:#1A1A1A;font-family:'Lato',sans-serif;">
            {{ $offer->offerRequest->company?->name ?? '—' }}
        </div>
        <div style="font-size:12px;color:#999;margin-top:2px;">
            Zapytanie #{{ $offer->offerRequest->id }}
        </div>
        <div style="margin-top:8px;">
            <span class="badge badge-blue">{{ $offer->offerRequest->status }}</span>
        </div>
    </div>
</div>
@endif

{{-- Metadane --}}
<div class="show-card">
    <div class="show-card-header">
        <i class="ti ti-info-circle"></i>
        <span class="show-card-title">Informacje</span>
    </div>
    <div class="show-card-body">
        <div style="font-size:12px;color:#888;margin-bottom:8px;font-family:'Manrope',sans-serif;">
            <div style="margin-bottom:6px;">
                <span style="font-weight:700;">Utworzył:</span><br>
                <span style="color:#333;">{{ $offer->createdBy?->name ?? '—' }}</span>
            </div>
            <div style="margin-bottom:6px;">
                <span style="font-weight:700;">Data utworzenia:</span><br>
                <span style="color:#333;">{{ $offer->created_at->format('d.m.Y') }}</span>
            </div>
            <div>
                <span style="font-weight:700;">Ostatnia aktualizacja:</span><br>
                <span style="color:#333;">{{ $offer->updated_at->format('d.m.Y H:i') }}</span>
            </div>
        </div>
    </div>
</div>

</div>{{-- /sidebar --}}
</div>{{-- /show-layout --}}

@endsection

@push('scripts')
<script>
    function toggleWonAs(select) {
        const section = document.getElementById('wonAsSection');
        section.style.display = select.value === 'wygrana' ? 'block' : 'none';
    }
</script>
@endpush
