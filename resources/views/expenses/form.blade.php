@extends('layouts.app')
@section('page-title','Rozliczenia faktur — dane zakupu')
@section('content')
@include('expenses.style')
<div class="exp-head"><h1>{{$invoice->exists ? 'Faktura / zakup #'.$invoice->id : 'Dodaj zakup / fakturę'}}</h1><a class="exp-btn" href="{{route('expenses.index')}}">Wróć do rejestru</a></div>
@if(session('success'))<div class="exp-message">{{session('success')}}</div>@endif
@if($errors->any())<div class="exp-message error" role="alert">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
@if($invoice->is_unassigned)<div class="exp-card"><strong>Faktura do rozpoznania</strong><p>Ten zakup jest Twój? Po przypisaniu faktura będzie widoczna tylko dla Ciebie i księgowości.</p><form method="POST" action="{{route('expenses.claim',$invoice)}}" onsubmit="return confirm('Potwierdzasz, że to Twój zakup i chcesz przypisać fakturę do siebie?')">@csrf<input type="hidden" name="confirm" value="1"><button class="exp-btn primary">To mój zakup — przypisz do mnie</button></form></div>@endif
@if($editable)
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
@if($accounting)
@php($ownerMode = old('owner_mode', $invoice->exists ? ($invoice->is_unassigned ? 'pool' : ($invoice->owner_id ? 'user' : 'manual')) : 'user'))
<label class="exp-field">Kupujący — sposób przypisania<select name="owner_mode"><option value="user" @selected($ownerMode==='user')>Użytkownik z bazy</option><option value="manual" @selected($ownerMode==='manual')>Wpisz ręcznie</option><option value="pool" @selected($ownerMode==='pool')>Nieustalony — do przypisania</option></select><small>Tylko „Nieustalony” udostępnia fakturę wszystkim pracownikom. Ręcznie wpisana osoba nie otrzymuje dostępu do aplikacji.</small></label>
<label class="exp-field" data-owner-user>Kupujący — użytkownik<select name="owner_id"><option value="">Wybierz osobę</option>@foreach($employees as $employee)<option value="{{$employee->id}}" @selected((string)old('owner_id',$invoice->exists ? $invoice->owner_id : auth()->id())===(string)$employee->id)>{{$employee->name}}</option>@endforeach</select></label>
<label class="exp-field" data-owner-manual>Kupujący — imię i nazwisko<input name="owner_name" value="{{old('owner_name',$invoice->owner_name)}}" maxlength="255"></label>
@else<label class="exp-field">Kupujący<input value="{{$invoice->owner_name ?: auth()->user()->name}}" readonly></label>@endif
<label class="exp-field">Data zakupu *<input name="purchase_date" type="date" value="{{old('purchase_date',$invoice->purchase_date?->toDateString())}}" required></label>
<input type="hidden" name="wz_number" value="{{old('wz_number',$invoice->wz_number)}}">
<label class="exp-field">Dostawca *<input id="expense-supplier" name="supplier_name" list="expense-suppliers" value="{{old('supplier_name',$invoice->supplier_name)}}" maxlength="255" required placeholder="Wybierz z podpowiedzi lub wpisz nazwę" autocomplete="off"><datalist id="expense-suppliers">@foreach($suppliers as $supplier)<option value="{{$supplier->name}}" data-nip="{{$supplier->nip}}">{{$supplier->nip ? 'NIP '.$supplier->nip : ''}}</option>@endforeach</datalist></label>
@foreach(['invoice_number'=>'Numer faktury (jeśli znany)','ksef_number'=>'Numer KSeF (jeśli znany)','supplier_nip'=>'NIP dostawcy','cost_center'=>'Miejsce kosztu / kod budowy','paid_by'=>'Kto zapłacił'] as $field=>$label)<label class="exp-field">{{$label}}<input name="{{$field}}" value="{{old($field,$invoice->$field)}}" maxlength="{{$field==='ksef_number'?100:($field==='supplier_nip'?20:255)}}"></label>@endforeach
<label class="exp-field">Stawka VAT do przeliczenia<select name="vat_rate"><option value="">Bez przeliczania / różne stawki</option>@foreach([23,8,5,0] as $rate)<option value="{{$rate}}" @selected((string)old('vat_rate',$invoice->vat_rate !== null ? (string)(float)$invoice->vat_rate : 23)===(string)$rate)>{{$rate}}%</option>@endforeach</select><small>Zmiana netto lub brutto przelicza drugą kwotę według tej stawki.</small></label>
@foreach(['net_amount'=>'Kwota netto PLN','gross_amount'=>'Kwota brutto PLN'] as $field=>$label)<label class="exp-field">{{$label}}<input name="{{$field}}" type="number" step="0.01" min="0" value="{{old($field,$invoice->$field)}}"></label>@endforeach
@foreach(['payment_method'=>['Sposób płatności',\App\Models\ExpenseInvoice::METHODS],'payment_source'=>['Źródło środków',\App\Models\ExpenseInvoice::SOURCES],'payment_status'=>['Status zapłaty',\App\Models\ExpenseInvoice::PAYMENTS]] as $field=>$options)<label class="exp-field">{{$options[0]}}<select name="{{$field}}">@foreach($options[1] as $key=>$label)@if($key!=='settled' || $accounting || $invoice->settled_at)<option value="{{$key}}" @selected(old($field,$field==='payment_status' && $invoice->settled_at ? 'settled' : $invoice->$field)===$key)>{{$label}}</option>@endif @endforeach</select>@if($field==='payment_status' && $accounting)<small>„Rozliczona — zamknięta” kończy sprawę i blokuje dalszą edycję po zapisaniu.</small>@endif</label>@endforeach
<label class="exp-field wide">Opis zakupu / uwagi<textarea name="description" rows="3" maxlength="5000">{{old('description',$invoice->description)}}</textarea></label>
@if($accounting)<label class="exp-field wide">Uwagi księgowości<textarea name="accounting_note" rows="3" maxlength="5000">{{old('accounting_note',$invoice->accounting_note)}}</textarea></label>@endif
</div>@if($editable)<p><button class="exp-btn primary">Zapisz dane zakupu</button></p>@endif</fieldset>
@if(!$editable)<p class="exp-muted">{{$invoice->settled_at ? 'Faktura została rozliczona i jest zamknięta do edycji.' : 'Przypisz fakturę do siebie, aby uzupełnić dane swojego zakupu.'}}</p>@endif
</form>
@if($invoice->exists)@include('expenses.delete')@endif
@endsection
@push('scripts')<script src="{{asset('js/expense-scan.js')}}?v=2" defer></script><script type="module" src="{{asset('js/expense-form.js')}}?v=2"></script>@endpush
