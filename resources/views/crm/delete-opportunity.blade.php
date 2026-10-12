@can('delete', $opp)
<form method="POST" action="{{ route('crm.opportunities.destroy', $opp) }}" style="display:inline;margin:0" onclick="event.stopPropagation()" onsubmit="return confirm('Usunąć tę szansę z CRM? Powiązane oferty i zadania pozostaną zachowane.')">
    @csrf @method('DELETE')
    <button type="submit" class="btn-icon btn-icon-delete" title="Usuń szansę" aria-label="Usuń szansę"><i class="ti ti-trash"></i></button>
</form>
@endcan
