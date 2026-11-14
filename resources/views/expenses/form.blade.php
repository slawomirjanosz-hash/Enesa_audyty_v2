@extends('layouts.app')
@section('page-title','Rozliczenia faktur — dane zakupu')
@section('content')
@include('expenses.style')
<style>#expense-form [hidden]{display:none!important}#expense-form .exp-grid{grid-template-columns:minmax(0,1fr);max-width:36rem}#expense-form .exp-field>label{display:flex;flex-direction:column;gap:6px}</style>
<div class="exp-head"><h1>{{$invoice->exists ? 'Faktura / zakup #'.$invoice->id : ($accountingEntry ? 'Dodaj fakturę jako księgowość' : 'Dodaj zakup / fakturę')}}</h1><a class="exp-btn" href="{{route('expenses.index')}}">Wróć do rejestru</a></div>
@if(session('success'))<div class="exp-message">{{session('success')}}</div>@endif
@if($errors->any())<div class="exp-message error" role="alert">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
@if($invoice->is_unassigned)<div class="exp-card"><strong>Faktura do rozpoznania</strong><p>Ten zakup jest Twój? Po przypisaniu faktura będzie widoczna tylko dla Ciebie i księgowości.</p>@include('expenses.claim')</div>@endif
@include('expenses.workflow')
<form class="exp-card" method="POST" action="{{$invoice->exists ? route('expenses.update',$invoice) : route('expenses.store')}}" id="expense-form" data-form-draft="false" data-entry-wizard="{{!$invoice->exists && !$errors->any() && $editable ? '1' : '0'}}">@csrf @if($accountingEntry)<input type="hidden" name="accounting_entry" value="1">@endif @if($invoice->exists)@method('PUT')<input type="hidden" name="revision" value="{{$invoice->revision}}">@endif
<fieldset style="border:0;padding:0" @disabled(!$editable)><div class="exp-grid">
@include('expenses.entry-fields')
@if($manageOwner)
<div class="exp-field" data-expense-step>
@php($ownerMode = old('owner_mode', $invoice->exists ? ($invoice->is_unassigned ? 'pool' : ($invoice->owner_id ? 'user' : 'manual')) : 'pool'))
<label class="exp-field">Kupujący — sposób przypisania<select name="owner_mode"><option value="user" @selected($ownerMode==='user')>Użytkownik z bazy</option><option value="manual" @selected($ownerMode==='manual')>Wpisz ręcznie</option><option value="pool" @selected($ownerMode==='pool')>Nieustalony — do przypisania</option></select><small>Tylko „Nieustalony” udostępnia fakturę wszystkim pracownikom. Ręcznie wpisana osoba nie otrzymuje dostępu do aplikacji.</small></label>
<label class="exp-field" data-owner-user>Kupujący — użytkownik<select name="owner_id"><option value="">Wybierz osobę</option>@foreach($employees as $employee)<option value="{{$employee->id}}" @selected((string)old('owner_id',$invoice->owner_id)===(string)$employee->id)>{{$employee->name}}</option>@endforeach</select></label>
<label class="exp-field" data-owner-manual>Kupujący — imię i nazwisko<input name="owner_name" value="{{old('owner_name',$invoice->owner_name)}}" maxlength="255"></label>
</div>
@else
<p class="exp-field">Kupujący <strong>{{ $invoice->exists ? ($invoice->owner_name ?: 'Nieprzypisana') : auth()->user()->name }}</strong></p>
@endif
</div>@if($editable)<p class="exp-actions">
    @unless($invoice->exists)<button type="button" class="exp-btn" data-expense-back hidden>Wstecz</button><button type="button" class="exp-btn primary" data-expense-next hidden>Dalej</button><small data-expense-progress aria-live="polite"></small>@endunless
    <button class="exp-btn primary" type="submit">Zapisz fakturę</button>
</p>@endif</fieldset>
@if(!$editable)<p class="exp-muted">{{$invoice->settled_at ? 'Faktura została rozliczona i jest zamknięta do edycji.' : 'Przypisz fakturę do siebie, aby uzupełnić dane swojego zakupu.'}}</p>@endif
</form>
@if($invoice->exists)@include('expenses.legacy-details')@include('expenses.delete')@endif
@endsection
@push('scripts')<script type="module" src="{{asset('js/expense-form.js')}}?v=4"></script>@endpush
