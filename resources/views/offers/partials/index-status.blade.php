@php
    $archiveOrigin = $offer->archived_from_status;
    $colorStatus = $isArchive ? $archiveOrigin : $offer->status;
    $badgeClass = match($colorStatus) {
        'wygrana' => 'badge-green',
        'przegrana' => 'badge-red',
        'w_toku', 'w_negocjacji' => $isArchive ? 'badge-gray' : 'badge-blue',
        default => 'badge-gray',
    };
    $statusLabel = $statusLabels[$offer->status] ?? $offer->status;
    if ($isArchive && $archiveOrigin) {
        $statusLabel .= ' · '.($statusLabels[$archiveOrigin] ?? $archiveOrigin);
    }
@endphp
@can('update', $offer)
    <form method="POST" action="{{ route('offers.status', $offer) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="new_status" value="">
        <select name="status" class="badge {{ $badgeClass }} offer-status-select" aria-label="Status oferty {{ $offer->fullNumber() }}" title="{{ $statusLabel }}" data-offer-status-select data-current-status="{{ $offer->status }}">
            @foreach($statusLabels as $value => $label)
                <option value="{{ $value }}" @selected($offer->status === (string) $value)>{{ $offer->status === (string) $value ? $statusLabel : $label }}</option>
            @endforeach
            <option value="__add">+ Dodaj status</option>
        </select>
    </form>
@else
    <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
@endcan
