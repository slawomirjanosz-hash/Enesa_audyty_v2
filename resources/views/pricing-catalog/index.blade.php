@extends('layouts.app')

@section('page-title', 'Cennik usług')

@push('styles')
<style>
.pricing-grid { display:grid; grid-template-columns:340px 1fr; gap:20px; align-items:start; }
.price-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; }
.price-card-head { padding:14px 18px; background:#FAFAF6; border-bottom:1px solid #F0EDE6; font:700 14px 'Manrope',sans-serif; color:#1A1A1A; }
.price-card-body { padding:18px; }
.price-label { display:block; margin:0 0 5px; font:700 11px 'Manrope',sans-serif; color:#555; }
.price-input { width:100%; box-sizing:border-box; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:8px 10px; font:13px 'Lato',sans-serif; color:#1A1A1A; }
.price-input:focus { outline:none; background:#fff; border-color:var(--green); }
.price-group { margin-bottom:13px; }
.btn-primary { display:inline-flex; align-items:center; justify-content:center; gap:6px; background:var(--green); color:#F5F0E8; border:0; border-radius:8px; padding:9px 16px; font:700 13px 'Manrope',sans-serif; cursor:pointer; text-decoration:none; }
.btn-primary:hover { background:#143d2d; }
.btn-secondary { display:inline-flex; align-items:center; gap:6px; background:#fff; color:#333; border:1px solid #D0CCC0; border-radius:7px; padding:7px 10px; font:600 12px 'Manrope',sans-serif; cursor:pointer; }
.catalog-table { width:100%; border-collapse:collapse; font:13px 'Lato',sans-serif; }
.catalog-table th { padding:10px 14px; text-align:left; font:700 11px 'Manrope',sans-serif; text-transform:uppercase; letter-spacing:.05em; color:#888; background:#FAFAF6; border-bottom:1px solid #F0EDE6; }
.catalog-table td { padding:12px 14px; border-bottom:1px solid #F7F5F0; vertical-align:middle; }
.catalog-table tr:last-child td { border-bottom:0; }
.price-active { color:#166534; background:#DCFCE7; border-radius:20px; padding:3px 9px; font:700 11px 'Manrope',sans-serif; }
.price-inactive { color:#92400E; background:#FEF3C7; border-radius:20px; padding:3px 9px; font:700 11px 'Manrope',sans-serif; }
.price-code { color:#888; font:600 11px 'Manrope',sans-serif; margin-top:2px; }
.edit-row { display:none; background:#FFFBEB; }
.edit-row.open { display:table-row; }
@media(max-width:767px) { .pricing-grid { grid-template-columns:1fr; } .catalog-table { min-width:680px; } .price-card { overflow-x:auto; } }
</style>
@endpush

@section('content')
<div style="margin-bottom:20px;">
    <h1 style="margin:0;color:var(--green);font:700 22px 'Manrope',sans-serif;"><i class="ti ti-currency-zloty" style="margin-right:8px;"></i>Cennik usług</h1>
    <p style="margin:6px 0 0;color:#777;font-size:13px;">Wewnętrzne ceny netto używane do tworzenia wstępnej wyceny z ankiety. Klient ich nie widzi.</p>
</div>

@if(session('success'))
<div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:11px 16px;margin-bottom:16px;font-size:13px;"><i class="ti ti-circle-check"></i> {{ session('success') }}</div>
@endif

<div class="pricing-grid">
    <div class="price-card">
        <div class="price-card-head"><i class="ti ti-plus" style="color:var(--green);margin-right:6px;"></i>Nowa pozycja</div>
        <form class="price-card-body" method="POST" action="{{ route('pricing-catalog.store') }}">
            @csrf
            <div class="price-group"><label class="price-label">Nazwa usługi *</label><input class="price-input" name="name" required placeholder="np. Wizja lokalna"></div>
            <div class="price-group"><label class="price-label">Kod (opcjonalnie)</label><input class="price-input" name="code" placeholder="np. WIZJA_LOK"></div>
            <div class="price-group"><label class="price-label">Opis wewnętrzny</label><textarea class="price-input" name="description" rows="3" placeholder="Kiedy stosować tę pozycję..."></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;" class="price-group">
                <div><label class="price-label">Jednostka *</label><input class="price-input" name="unit" value="usługa" required></div>
                <div><label class="price-label">Cena netto *</label><input class="price-input" name="net_unit_price" type="number" min="0" step="0.01" required></div>
            </div>
            <input type="hidden" name="is_active" value="0">
            <label style="display:flex;gap:7px;align-items:center;margin-bottom:16px;font-size:12px;cursor:pointer;"><input type="checkbox" name="is_active" value="1" checked style="accent-color:var(--green);"> Pozycja aktywna</label>
            <button class="btn-primary" style="width:100%;" type="submit"><i class="ti ti-device-floppy"></i> Dodaj do cennika</button>
        </form>
    </div>

    <div class="price-card">
        <div class="price-card-head"><i class="ti ti-list" style="color:var(--green);margin-right:6px;"></i>Pozycje cennika ({{ $items->count() }})</div>
        @if($items->isEmpty())
            <div style="padding:42px;text-align:center;color:#888;font-size:13px;">Dodaj pierwszą pozycję, aby móc połączyć ją z odpowiedzią w ankiecie.</div>
        @else
        <table class="catalog-table">
            <thead><tr><th>Usługa</th><th>Jednostka</th><th>Cena netto</th><th>Status</th><th style="text-align:right;">Akcje</th></tr></thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    <td><strong>{{ $item->name }}</strong>@if($item->code)<div class="price-code">{{ $item->code }}</div>@endif</td>
                    <td>{{ $item->unit }}</td>
                    <td style="font-weight:700;color:var(--green);">{{ number_format($item->net_unit_price, 2, ',', ' ') }} zł</td>
                    <td><span class="{{ $item->is_active ? 'price-active' : 'price-inactive' }}">{{ $item->is_active ? 'Aktywna' : 'Wyłączona' }}</span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="btn-secondary" onclick="document.getElementById('edit-{{ $item->id }}').classList.toggle('open')"><i class="ti ti-pencil"></i> Edytuj</button>
                        <form method="POST" action="{{ route('pricing-catalog.toggle', $item) }}" style="display:inline;">@csrf @method('PATCH')<button class="btn-secondary" type="submit">{{ $item->is_active ? 'Wyłącz' : 'Włącz' }}</button></form>
                    </td>
                </tr>
                <tr id="edit-{{ $item->id }}" class="edit-row"><td colspan="5">
                    <form method="POST" action="{{ route('pricing-catalog.update', $item) }}" style="display:grid;grid-template-columns:1.3fr .7fr .5fr .7fr auto;gap:8px;align-items:end;">@csrf @method('PUT')
                        <div><label class="price-label">Nazwa</label><input class="price-input" name="name" value="{{ $item->name }}" required></div>
                        <div><label class="price-label">Kod</label><input class="price-input" name="code" value="{{ $item->code }}"></div>
                        <div><label class="price-label">Jednostka</label><input class="price-input" name="unit" value="{{ $item->unit }}" required></div>
                        <div><label class="price-label">Cena netto</label><input class="price-input" type="number" min="0" step="0.01" name="net_unit_price" value="{{ $item->net_unit_price }}" required></div>
                        <input type="hidden" name="description" value="{{ $item->description }}"><input type="hidden" name="is_active" value="{{ $item->is_active ? '1' : '0' }}">
                        <button class="btn-primary" type="submit"><i class="ti ti-device-floppy"></i> Zapisz</button>
                    </form>
                </td></tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
