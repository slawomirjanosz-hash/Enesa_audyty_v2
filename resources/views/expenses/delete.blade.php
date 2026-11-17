@if($invoice->canBeDeletedBy(auth()->user(), $accounting))
<form method="POST" action="{{route('expenses.destroy',$invoice)}}" onsubmit="return confirm('Usunąć tę fakturę z rejestru?')" class="exp-delete-form">
@csrf @method('DELETE') @if(($tab ?? request('tab')) === 'all')<input type="hidden" name="tab" value="all">@endif<input type="hidden" name="confirm" value="1"><input type="hidden" name="revision" value="{{$invoice->revision}}">
<button @class(['exp-btn', 'danger', 'exp-icon-btn' => $iconOnly ?? false]) aria-label="Usuń" type="submit" title="Usuń fakturę {{$invoice->invoice_number ?: '#'.$invoice->id}}">@if($iconOnly ?? false)<i class="ti ti-trash" aria-hidden="true"></i>@else Usuń @endif</button>
</form>
@endif
