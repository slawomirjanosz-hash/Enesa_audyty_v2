@extends('layouts.client')

@section('page-title', 'Podgląd zapytania')

@section('content')

<div style="padding: 20px;">

    {{-- Nagłówek --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div style="flex: 1;">
            <a href="{{ route('client.dashboard') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #1A4D3A; text-decoration: none; font-weight: 600; margin-bottom: 12px;">
                <i class="ti ti-arrow-left"></i> Wróć do dashboarda
            </a>
            <h1 style="font-family: 'Manrope', sans-serif; font-size: 22px; font-weight: 700; color: #1A1A1A; margin: 0;">
                {{ $offerRequest->offerFormTemplate?->name ?? 'Zapytanie' }}
            </h1>
            <div style="font-size: 13px; color: #888; margin-top: 6px;">
                Zapytanie #{{ $offerRequest->id }} · {{ $offerRequest->created_at->format('d.m.Y H:i') }}
            </div>
        </div>
        <div>
            @php
                $statusData = [
                    'nowe' => ['color' => '#DBEAFE', 'text' => '#1D4ED8', 'label' => 'Nowe'],
                    'w_toku' => ['color' => '#FEF3C7', 'text' => '#92400E', 'label' => 'W toku'],
                    'zamknięte' => ['color' => '#D1D5DB', 'text' => '#374151', 'label' => 'Zamknięte'],
                ];
                $status = $statusData[$offerRequest->status] ?? $statusData['nowe'];
            @endphp
            <span style="background: {{ $status['color'] }}; color: {{ $status['text'] }}; border-radius: 20px; padding: 6px 14px; font-size: 12px; font-weight: 700; font-family: 'Manrope', sans-serif;">
                {{ $status['label'] }}
            </span>
        </div>
    </div>

    {{-- Odpowiedzi formularza --}}
    <div style="background: #fff; border: 1px solid #E5E1D8; border-radius: 12px; padding: 22px; margin-bottom: 20px;">
        <div style="font-family: 'Manrope', sans-serif; font-size: 14px; font-weight: 700; color: #1A4D3A; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="ti ti-clipboard-list"></i> Odpowiedzi
        </div>

        @php
            $fields = $offerRequest->offerFormTemplate?->flatFields() ?? [];
            $responses = $offerRequest->form_responses ?? [];
            $fieldMap = collect($fields)->keyBy('key');
        @endphp

        @if(empty($responses))
            <p style="color: #888; font-size: 13px;">Brak odpowiedzi w tym zapytaniu.</p>
        @else
            @foreach($responses as $key => $value)
                @php
                    $field = $fieldMap->get($key);
                    $label = $field['label'] ?? $key;
                @endphp
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 11px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
                        {{ $label }}
                    </div>
                    <div style="background: #FAFAF6; border: 1px solid #E5E1D8; border-radius: 8px; padding: 12px; font-size: 13px; color: #1A1A1A; word-break: break-word; line-height: 1.5;">
                        {{ \App\Models\OfferFormTemplate::displayValue($value) ?: '—' }}
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Dodatkowe notatki --}}
    @if($offerRequest->tresc)
        <div style="background: #fff; border: 1px solid #E5E1D8; border-radius: 12px; padding: 22px; margin-bottom: 20px;">
            <div style="font-family: 'Manrope', sans-serif; font-size: 14px; font-weight: 700; color: #1A4D3A; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <i class="ti ti-note"></i> Dodatkowe informacje
            </div>
            <div style="background: #FAFAF6; border: 1px solid #E5E1D8; border-radius: 8px; padding: 14px; font-size: 13px; color: #1A1A1A; white-space: pre-wrap; word-break: break-word; line-height: 1.6;">
                {{ $offerRequest->tresc }}
            </div>
        </div>
    @endif

</div>

@endsection
