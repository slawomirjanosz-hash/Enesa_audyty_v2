@extends('layouts.app')
@section('page-title','Rozliczenia faktur — dane zakupu')
@section('content')
@include('expenses.style')
<style>#expense-form.exp-awaiting-choice,#expense-form [hidden]{display:none!important}</style>
<div class="exp-head"><h1>{{$invoice->exists ? 'Faktura / zakup #'.$invoice->id : 'Dodaj zakup / fakturę'}}</h1><a class="exp-btn" href="{{route('expenses.index')}}">Wróć do rejestru</a></div>
@if(session('success'))<div class="exp-message">{{session('success')}}</div>@endif
@if($errors->any())<div class="exp-message error" role="alert">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
@if($invoice->is_unassigned)<div class="exp-card"><strong>Faktura do rozpoznania</strong><p>Ten zakup jest Twój? Po przypisaniu faktura będzie widoczna tylko dla Ciebie i księgowości.</p><form method="POST" action="{{route('expenses.claim',$invoice)}}" onsubmit="return confirm('Potwierdzasz, że to Twój zakup i chcesz przypisać fakturę do siebie?')">@csrf<input type="hidden" name="confirm" value="1"><button class="exp-btn primary">To mój zakup — przypisz do mnie</button></form></div>@endif
@include('expenses.workflow')
@if($editable && !$invoice->exists)
<section class="exp-card"><h2>Dodaj dokument zakupu</h2>
<form id="expense-scan" action="{{route('expenses.scan')}}" method="POST" enctype="multipart/form-data" data-form-draft="false">@csrf
<div class="exp-entry-actions">
<button class="exp-btn primary exp-camera" type="button" data-photo-pick="expense-camera"><i class="ti ti-camera" aria-hidden="true"></i> Zrób zdjęcie</button>
<button class="exp-btn" type="button" data-photo-pick="expense-photo"><i class="ti ti-photo" aria-hidden="true"></i><span class="exp-mobile-label">Wybierz z galerii</span><span class="exp-desktop-label">Wybierz zdjęcie z dysku</span></button>
<button class="exp-btn" type="button" id="expense-manual"><i class="ti ti-pencil" aria-hidden="true"></i> Wprowadź ręcznie</button>
</div>
<input id="expense-camera" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" hidden aria-label="Zrób zdjęcie dokumentu">
<input id="expense-photo" type="file" accept="image/jpeg,image/png,image/webp" hidden aria-label="Wybierz zdjęcie dokumentu">
<p class="exp-muted">Po wybraniu zdjęcia odczyt ruszy automatycznie. JPG, PNG lub WebP, maks. 5 MB.</p>
<p class="exp-muted">Zdjęcie wysyłamy do Anthropic do odczytu; nie przechowujemy go w aplikacji.</p>
<p id="expense-scan-status" class="exp-scan-status" role="status" aria-live="polite"></p></form></section>
@endif
<form class="exp-card" method="POST" action="{{$invoice->exists ? route('expenses.update',$invoice) : route('expenses.store')}}" id="expense-form" data-form-draft="false" data-entry-choice="{{!$invoice->exists && !$errors->any() && $editable ? '1' : '0'}}" data-existing="{{$invoice->exists ? '1' : '0'}}">@csrf @if($invoice->exists)@method('PUT')<input type="hidden" name="revision" value="{{$invoice->revision}}">@endif
<fieldset style="border:0;padding:0" @disabled(!$editable)><div class="exp-grid">
@if($accounting && $invoice->exists)
@php($ownerMode = old('owner_mode', $invoice->exists ? ($invoice->is_unassigned ? 'pool' : ($invoice->owner_id ? 'user' : 'manual')) : 'user'))
<label class="exp-field">Kupujący — sposób przypisania<select name="owner_mode"><option value="user" @selected($ownerMode==='user')>Użytkownik z bazy</option><option value="manual" @selected($ownerMode==='manual')>Wpisz ręcznie</option><option value="pool" @selected($ownerMode==='pool')>Nieustalony — do przypisania</option></select><small>Tylko „Nieustalony” udostępnia fakturę wszystkim pracownikom. Ręcznie wpisana osoba nie otrzymuje dostępu do aplikacji.</small></label>
<label class="exp-field" data-owner-user>Kupujący — użytkownik<select name="owner_id"><option value="">Wybierz osobę</option>@foreach($employees as $employee)<option value="{{$employee->id}}" @selected((string)old('owner_id',$invoice->exists ? $invoice->owner_id : auth()->id())===(string)$employee->id)>{{$employee->name}}</option>@endforeach</select></label>
<label class="exp-field" data-owner-manual>Kupujący — imię i nazwisko<input name="owner_name" value="{{old('owner_name',$invoice->owner_name)}}" maxlength="255"></label>
@endif
@include('expenses.entry-fields')
</div>@if($editable)<p class="exp-actions">
    @unless($invoice->exists)<button type="button" class="exp-btn" data-expense-back hidden>Wstecz</button><button type="button" class="exp-btn primary" data-expense-next hidden>Dalej</button><small data-expense-progress aria-live="polite"></small>@endunless
    <button class="exp-btn primary" type="submit">Zapisz fakturę</button>
</p>@endif</fieldset>
@if(!$editable)<p class="exp-muted">{{$invoice->settled_at ? 'Faktura została rozliczona i jest zamknięta do edycji.' : 'Przypisz fakturę do siebie, aby uzupełnić dane swojego zakupu.'}}</p>@endif
</form>
@if($invoice->exists)@include('expenses.delete')@endif
@endsection
@push('scripts')<script src="{{asset('js/expense-scan.js')}}?v=3" defer></script><script type="module" src="{{asset('js/expense-form.js')}}?v=3"></script>@endpush
