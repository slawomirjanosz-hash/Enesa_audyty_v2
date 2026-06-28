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
            $fields = $offerRequest->offerFormTemplate?->fields ?? [];
            $responses = $offerRequest->form_responses ?? [];
            $fieldMap = collect($fields)->keyBy('key');
        @endphp

        @if(empty($responses))
            <p style="color:#888;font-size:13px;">Brak odpowiedzi.</p>
        @else
            @foreach($responses as $key => $value)
                @php $field = $fieldMap->get($key); @endphp
                <div style="margin-bottom:14px;">
                    <div style="font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">
                        {{ $field['label'] ?? $key }}
                    </div>
                    <div style="background:#FAFAF6;border:1px solid #E5E1D8;border-radius:7px;padding:10px 12px;font-size:13px;color:#1A1A1A;word-break:break-word;">
                        {{ $value ?: '—' }}
                    </div>
                </div>
            @endforeach
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
