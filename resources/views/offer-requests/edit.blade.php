@extends('layouts.app')

@section('page-title', 'Edytuj zapytanie')

@push('styles')
<style>
.page-header { margin-bottom:24px; }
.page-header h1 { font-family:'Manrope',sans-serif; font-size:20px; font-weight:700; color:#1A4D3A; margin:0 0 4px; display:flex; align-items:center; gap:8px; }
.page-header p { font-size:13px; color:#888; margin:0; }
.form-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:20px; }
.form-card-header { padding:14px 20px; background:#FAFAF6; border-bottom:1px solid #F0EDE6; display:flex; align-items:center; gap:10px; }
.form-card-header i { color:#1A4D3A; font-size:17px; }
.form-card-title { font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; }
.form-card-body { padding:20px; }
.field-label { display:block; font-family:'Manrope',sans-serif; font-size:12px; font-weight:700; color:#3a3a3a; margin-bottom:5px; }
.field-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:9px 12px; font-size:14px; font-family:'Lato',sans-serif; color:#1A1A1A; outline:none; transition:border-color .15s; box-sizing:border-box; }
.field-input:focus { border-color:#1A4D3A; background:#fff; }
.field-group { margin-bottom:16px; }
.btn-primary { display:inline-flex; align-items:center; gap:7px; background:#1A4D3A; color:#F5F0E8; border:none; border-radius:8px; padding:10px 20px; font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:background .15s; }
.btn-primary:hover { background:#143d2d; }
.btn-secondary { display:inline-flex; align-items:center; gap:7px; background:#fff; color:#333; border:1px solid #D0CCC0; border-radius:8px; padding:9px 18px; font-family:'Manrope',sans-serif; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-secondary:hover { background:#F4F1EA; }
.alert-error { background:#FEF2F2; border:1px solid #FCA5A5; color:#B91C1C; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:13px; }
</style>
@endpush

@section('content')

<div class="page-header">
    <h1><i class="ti ti-pencil"></i>Edytuj zapytanie</h1>
    <p>{{ $offerRequest->company?->name }} — zapytanie #{{ $offerRequest->id }}</p>
</div>

@if($errors->any())
    <div class="alert-error">
        <strong>Popraw błędy:</strong>
        <ul style="margin:6px 0 0 16px;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('offer-requests.update', $offerRequest) }}">
    @csrf
    @method('PUT')

    @if($offerRequest->offerFormTemplate && !empty($offerRequest->offerFormTemplate->fields))
    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-clipboard-list"></i>
            <span class="form-card-title">{{ $offerRequest->offerFormTemplate->name }}</span>
        </div>
        <div class="form-card-body">
            @php $responses = $offerRequest->form_responses ?? []; @endphp
            @foreach($offerRequest->offerFormTemplate->flatFields() as $field)
                @continue(!isset($field['key']))
                <div class="field-group">
                    <label class="field-label">{{ $field['label'] ?? $field['key'] }}</label>
                    @if(($field['type'] ?? 'text') === 'select')
                        <select name="form_responses[{{ $field['key'] }}]" class="field-input">
                            <option value="">— wybierz —</option>
                            @foreach(($field['options'] ?? []) as $opt)
                                <option value="{{ $opt }}" {{ (old('form_responses.'.$field['key'], $responses[$field['key']] ?? '') == $opt) ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif(($field['type'] ?? 'text') === 'textarea')
                        <textarea name="form_responses[{{ $field['key'] }}]" class="field-input" rows="3">{{ old('form_responses.'.$field['key'], $responses[$field['key']] ?? '') }}</textarea>
                    @else
                        <input type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text') }}"
                               name="form_responses[{{ $field['key'] }}]"
                               class="field-input"
                               value="{{ old('form_responses.'.$field['key'], $responses[$field['key']] ?? '') }}">
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-mail"></i>
            <span class="form-card-title">Treść zapytania / wiadomość od klienta</span>
        </div>
        <div class="form-card-body">
            <textarea name="tresc" class="field-input" rows="6"
                      placeholder="Treść zapytania...">{{ old('tresc', $offerRequest->tresc) }}</textarea>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <a href="{{ route('offer-requests.show', $offerRequest) }}" class="btn-secondary">
            <i class="ti ti-x"></i> Anuluj
        </a>
        <button type="submit" class="btn-primary">
            <i class="ti ti-check"></i> Zapisz zmiany
        </button>
    </div>
</form>

@endsection
