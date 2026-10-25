@if($accounting || ($invoice->owner_id===auth()->id() && !$invoice->is_unassigned && !$invoice->settled_at))
<form method="POST" action="{{route('expenses.destroy',$invoice)}}" onsubmit="return confirm('Usunąć tę fakturę z rejestru?')" class="exp-delete-form">
@csrf @method('DELETE')<input type="hidden" name="confirm" value="1"><input type="hidden" name="revision" value="{{$invoice->revision}}">
<button class="exp-btn danger" type="submit" title="Usuń fakturę {{$invoice->invoice_number ?: '#'.$invoice->id}}">Usuń</button>
</form>
@endif
