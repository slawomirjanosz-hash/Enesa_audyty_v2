@php
    $canEdit = auth()->user()->can('update', $offer);
    $canViewPrices = auth()->user()->can('viewPrices', $offer);
    $canCopy = $canEdit && (auth()->user()->can('offers.create') || app(\App\Services\AuditorAccessService::class)->hasFullAccess(auth()->user()));
    $canDelete = auth()->user()->can('delete', $offer);
@endphp
<div class="offer-actions" data-action-icons="false">
    @if($canViewPrices)
        <button type="button" class="btn-icon btn-icon-pdf" title="Podgląd PDF" aria-label="Podgląd PDF"
                onclick="openPdfModal(@js(route('offers.pdf', $offer)), @js('Oferta '.$offer->fullNumber()))">
            <i class="ti ti-file-type-pdf" aria-hidden="true"></i>
        </button>
    @endif
    @if($canEdit)
        <a href="{{ route('offers.edit', $offer) }}" class="btn-icon btn-icon-edit" title="Edytuj" aria-label="Edytuj">
            <i class="ti ti-pencil" aria-hidden="true"></i>
        </a>
    @endif
    @if(($canEdit && $canViewPrices) || $canCopy || $canDelete)
        <select class="offer-more-actions" data-offer-action aria-label="Więcej akcji: {{ $offer->fullNumber() }}" title="Więcej akcji">
            <option value="">…</option>
            @if($canEdit && $canViewPrices)<option value="save">Zapisz PDF na dysku</option>@endif
            @if($canCopy)<option value="copy">Kopiuj ofertę</option>@endif
            @if($canDelete)<option value="delete">Usuń ofertę</option>@endif
        </select>
    @endif
    @if($canEdit && $canViewPrices)
        <form hidden method="POST" action="{{ route('offers.save-to-storage', $offer) }}" data-action="save">@csrf</form>
    @endif
    @if($canCopy)
        <form hidden method="POST" action="{{ route('offers.clone', $offer) }}" data-action="copy">
            @csrf
            <input type="hidden" name="mode" value="offer">
        </form>
    @endif
    @if($canDelete)
        <form hidden method="POST" action="{{ route('offers.destroy', $offer) }}" data-action="delete"
              onsubmit="return confirm(@js('Czy na pewno chcesz usunąć ofertę '.$offer->fullNumber().'?'))">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>
