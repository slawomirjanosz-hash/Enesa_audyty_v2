@extends('layouts.app')

@section('page-title', 'Dostawcy')

@section('content')
<style>
.sup-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:20px}.sup-head h1{margin:0 0 5px;font-size:25px}.sup-head p{margin:0;color:#718078;font-size:13px}.sup-tools{display:flex;gap:8px;align-items:center}.sup-search{display:flex;background:#fff;border:1px solid #d9d5cc;border-radius:8px;overflow:hidden}.sup-search input{border:0;padding:9px 11px;width:280px;outline:0}.sup-search button,.view-button{border:0;background:var(--green);color:#fff;padding:9px 12px;cursor:pointer}.view-button{border-radius:7px;background:#edf4ef;color:var(--green)}.view-button.active{background:var(--green);color:#fff}.supplier-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.supplier-card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:17px;text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:12px;transition:.15s}.supplier-card:hover{border-color:#92b7a6;box-shadow:0 8px 24px rgba(21,57,41,.08);transform:translateY(-1px)}.supplier-top{display:flex;align-items:center;gap:11px}.supplier-logo{width:44px;height:44px;border-radius:10px;background:#edf4ef;color:var(--green);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;overflow:hidden}.supplier-logo img{width:100%;height:100%;object-fit:contain}.supplier-name{font-weight:800;font-size:14px}.supplier-meta{font-size:11px;color:#77827b;margin-top:3px}.supplier-copy{font-size:12px;color:#56635c;line-height:1.5;min-height:36px}.supplier-tags{display:flex;gap:6px;flex-wrap:wrap}.supplier-tag{padding:4px 7px;border-radius:999px;background:#f3f5f2;color:#526058;font-size:10px;font-weight:700}.supplier-foot{display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid #eee;font-size:11px;color:#6c786f}.supplier-list{display:none;background:#fff;border:1px solid #e5e1d8;border-radius:11px;overflow:auto}.supplier-list table{width:100%;border-collapse:collapse;min-width:850px}.supplier-list th,.supplier-list td{text-align:left;padding:11px 13px;border-bottom:1px solid #eee;font-size:12px}.supplier-list th{font-size:10px;text-transform:uppercase;background:#fafaf6;color:#7a847e}.supplier-list a{font-weight:800;color:var(--green);text-decoration:none}.empty{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:45px;text-align:center;color:#7d8781}.pagination-wrap{margin-top:18px}@media(max-width:1000px){.supplier-grid{grid-template-columns:1fr 1fr}}@media(max-width:700px){.sup-head{align-items:stretch;flex-direction:column}.sup-tools{flex-wrap:wrap}.sup-search{flex:1}.sup-search input{width:100%}.supplier-grid{grid-template-columns:1fr}}
</style>

<div class="sup-head">
    <div><h1>Dostawcy</h1><p>Firmy dostarczające materiały i usługi do realizowanych projektów.</p></div>
    <div class="sup-tools">
        <form class="sup-search" method="GET"><input name="q" value="{{request('q')}}" placeholder="Szukaj nazwy, miasta, materiału…"><button><i class="ti ti-search"></i></button></form>
        <button class="view-button active" id="supplier-grid-button" type="button" onclick="setSupplierView('grid')" title="Kafelki"><i class="ti ti-layout-grid"></i></button>
        <button class="view-button" id="supplier-list-button" type="button" onclick="setSupplierView('list')" title="Lista"><i class="ti ti-list"></i></button>
    </div>
</div>

@if($suppliers->isEmpty())
    <div class="empty"><i class="ti ti-truck-delivery" style="font-size:38px;display:block;margin-bottom:8px"></i>{{request('q') ? 'Nie znaleziono dostawców spełniających kryteria.' : 'Nie dodano jeszcze żadnego dostawcy. Dodaj firmę i wybierz typ „Dostawca”.'}}</div>
@else
    <div class="supplier-grid" id="supplier-grid">
        @foreach($suppliers as $supplier)
        @php
            $materials = collect(preg_split('/[,;\r\n]+/', $supplier->supplier_materials ?? '', -1, PREG_SPLIT_NO_EMPTY))->map(fn($item)=>trim($item))->filter()->take(4);
            $projectCount = $supplier->supplierRequirements->pluck('project_id')->merge($supplier->supplierFinancialEntries->pluck('project_id'))->filter()->unique()->count();
        @endphp
        <a class="supplier-card" href="{{route('suppliers.show',$supplier)}}">
            <div class="supplier-top"><div class="supplier-logo">@if($supplier->logoDataUri())<img src="{{$supplier->logoDataUri()}}" alt="">@else{{str($supplier->name)->substr(0,2)->upper()}}@endif</div><div><div class="supplier-name">{{$supplier->name}}</div><div class="supplier-meta">{{$supplier->city ?: 'Brak miasta'}} · NIP {{$supplier->nip ?: '—'}}</div></div></div>
            <div class="supplier-copy">{{str($supplier->supplier_capabilities ?: 'Nie uzupełniono jeszcze zakresu dostaw.')->limit(120)}}</div>
            <div class="supplier-tags">@forelse($materials as $material)<span class="supplier-tag">{{$material}}</span>@empty<span class="supplier-tag">Brak listy materiałów</span>@endforelse</div>
            <div class="supplier-foot"><span><i class="ti ti-package"></i> {{$supplier->supplier_requirements_count}} pozycji</span><span><i class="ti ti-folders"></i> {{$projectCount}} projektów</span></div>
        </a>
        @endforeach
    </div>
    <div class="supplier-list" id="supplier-list"><table><thead><tr><th>Dostawca</th><th>Kontakt</th><th>Co dostarcza</th><th>Pozycje</th><th>Projekty</th></tr></thead><tbody>@foreach($suppliers as $supplier)<tr><td><a href="{{route('suppliers.show',$supplier)}}">{{$supplier->name}}</a><br><small>{{$supplier->city ?: '—'}} · {{$supplier->nip ?: 'bez NIP'}}</small></td><td>{{$supplier->email ?: '—'}}<br><small>{{$supplier->phone}}</small></td><td>{{str($supplier->supplier_capabilities ?: $supplier->supplier_materials ?: '—')->limit(100)}}</td><td>{{$supplier->supplier_requirements_count}}</td><td>{{$supplier->supplierRequirements->pluck('project_id')->merge($supplier->supplierFinancialEntries->pluck('project_id'))->filter()->unique()->count()}}</td></tr>@endforeach</tbody></table></div>
    <div class="pagination-wrap">{{$suppliers->links()}}</div>
@endif

<script>
function setSupplierView(view){const grid=document.getElementById('supplier-grid'),list=document.getElementById('supplier-list');if(!grid||!list)return;grid.style.display=view==='grid'?'grid':'none';list.style.display=view==='list'?'block':'none';document.getElementById('supplier-grid-button').classList.toggle('active',view==='grid');document.getElementById('supplier-list-button').classList.toggle('active',view==='list');localStorage.setItem('supplierView',view)}
setSupplierView(localStorage.getItem('supplierView')||'grid');
</script>
@endsection
