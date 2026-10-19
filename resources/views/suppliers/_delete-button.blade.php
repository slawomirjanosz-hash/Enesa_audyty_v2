@if(!$supplier->is_owner && !$supplier->archived_at && auth()->user()->can('view', $supplier) && auth()->user()->can('delete', $supplier))
<form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" style="display:inline-flex;margin:0" onsubmit="return confirm('Usunąć dostawcę z aktywnej listy? Trafi do archiwum. Historia współpracy i rozliczeń zostanie zachowana.');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn-icon btn-icon-delete" title="Usuń dostawcę" aria-label="Usuń dostawcę {{ $supplier->name }}" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;border:1px solid #fecaca;border-radius:6px;background:#fff;color:#dc2626;cursor:pointer"><i class="ti ti-trash" aria-hidden="true"></i></button>
</form>
@endif
