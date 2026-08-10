@extends('layouts.app')

@section('page-title', 'Dostawcy')

@section('content')
<style>
.sup-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:20px}.sup-head h1{margin:0 0 5px;font-size:25px}.sup-head p{margin:0;color:#718078;font-size:13px}.sup-tools{display:flex;gap:8px;align-items:center}.sup-search{display:flex;background:#fff;border:1px solid #d9d5cc;border-radius:8px;overflow:hidden}.sup-search input{border:0;padding:9px 11px;width:280px;outline:0}.sup-search button,.view-button{border:0;background:var(--green);color:#fff;padding:9px 12px;cursor:pointer}.view-button{border-radius:7px;background:#edf4ef;color:var(--green)}.view-button.active{background:var(--green);color:#fff}.supplier-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.supplier-card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:17px;text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:12px;transition:.15s}.supplier-card:hover{border-color:#92b7a6;box-shadow:0 8px 24px rgba(21,57,41,.08);transform:translateY(-1px)}.supplier-top{display:flex;align-items:center;gap:11px}.supplier-logo{width:44px;height:44px;border-radius:10px;background:#edf4ef;color:var(--green);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;overflow:hidden}.supplier-logo img{width:100%;height:100%;object-fit:contain}.supplier-name{font-weight:800;font-size:14px}.supplier-meta{font-size:11px;color:#77827b;margin-top:3px}.supplier-copy{font-size:12px;color:#56635c;line-height:1.5;min-height:36px}.supplier-tags{display:flex;gap:6px;flex-wrap:wrap}.supplier-tag{padding:4px 7px;border-radius:999px;background:#f3f5f2;color:#526058;font-size:10px;font-weight:700}.supplier-foot{display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid #eee;font-size:11px;color:#6c786f}.supplier-list{display:none;background:#fff;border:1px solid #e5e1d8;border-radius:11px;overflow:auto}.supplier-list table{width:100%;border-collapse:collapse;min-width:850px}.supplier-list th,.supplier-list td{text-align:left;padding:11px 13px;border-bottom:1px solid #eee;font-size:12px}.supplier-list th{font-size:10px;text-transform:uppercase;background:#fafaf6;color:#7a847e}.supplier-list a{font-weight:800;color:var(--green);text-decoration:none}.empty{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:45px;text-align:center;color:#7d8781}.pagination-wrap{margin-top:18px}@media(max-width:1000px){.supplier-grid{grid-template-columns:1fr 1fr}}@media(max-width:700px){.sup-head{align-items:stretch;flex-direction:column}.sup-tools{flex-wrap:wrap}.sup-search{flex:1}.sup-search input{width:100%}.supplier-grid{grid-template-columns:1fr}}
.supplier-add{border:0;border-radius:8px;background:var(--green);color:#fff;padding:10px 13px;font-weight:700;cursor:pointer;white-space:nowrap}.supplier-modal{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:18px}.supplier-modal.open{display:flex}.supplier-modal-card{position:relative;width:min(540px,100%);max-height:92vh;overflow:auto;background:#fff;border-radius:14px;padding:30px}.supplier-modal-card h2{margin:0 0 5px;color:var(--green);font-size:19px}.supplier-modal-card>p{margin:0 0 20px;color:#78827c;font-size:13px}.supplier-modal-close{position:absolute;right:15px;top:11px;border:0;background:none;color:#8a938e;font-size:25px;cursor:pointer}.supplier-modal-card label{display:block;margin:12px 0 5px;font-size:12px;font-weight:700;color:#39433e}.supplier-modal-card input,.supplier-modal-card select,.supplier-modal-card textarea{box-sizing:border-box;width:100%;border:1px solid #d0ccc0;border-radius:7px;background:#fafaf6;padding:10px 11px;font:inherit}.supplier-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}.supplier-nip-row{display:flex;gap:8px}.supplier-nip-row button{border:1px solid rgba(26,77,58,.25);border-radius:7px;background:rgba(26,77,58,.08);color:var(--green);padding:9px 12px;font-weight:700;white-space:nowrap;cursor:pointer}.supplier-gus-status{min-height:16px;margin-top:5px;font-size:12px;color:#7b8580}.supplier-modal-actions{display:flex;gap:9px;margin-top:20px}.supplier-modal-actions button{border-radius:8px;padding:11px 16px;font-weight:700;cursor:pointer}.supplier-submit{flex:1;border:0;background:var(--green);color:#fff}.supplier-cancel{border:1px solid #dedbd3;background:#fff;color:#6d7771}.supplier-form-errors{border:1px solid #fca5a5;border-radius:7px;background:#fef2f2;color:#b91c1c;padding:10px 13px;font-size:13px}.supplier-form-errors ul{margin:6px 0 0 18px;padding:0}@media(max-width:600px){.supplier-form-grid{grid-template-columns:1fr}.supplier-nip-row{align-items:stretch;flex-direction:column}.supplier-modal-card{padding:25px 20px}}
</style>

<div class="sup-head">
    <div><h1>Dostawcy</h1><p>Firmy dostarczające materiały i usługi do realizowanych projektów.</p></div>
    <div class="sup-tools">
        @if($canCreateSupplier)
            <button class="supplier-add" type="button" onclick="openSupplierModal()"><i class="ti ti-plus"></i> Dodaj dostawcę</button>
        @endif
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

@if($canCreateSupplier)
    @include('suppliers._create-modal')
@endif

<script>
function setSupplierView(view){const grid=document.getElementById('supplier-grid'),list=document.getElementById('supplier-list');if(!grid||!list)return;grid.style.display=view==='grid'?'grid':'none';list.style.display=view==='list'?'block':'none';document.getElementById('supplier-grid-button').classList.toggle('active',view==='grid');document.getElementById('supplier-list-button').classList.toggle('active',view==='list');localStorage.setItem('supplierView',view)}
setSupplierView(localStorage.getItem('supplierView')||'grid');
function openSupplierModal(){const modal=document.getElementById('supplier-create-modal');if(!modal)return;modal.classList.add('open');modal.setAttribute('aria-hidden','false');updateSupplierFields()}
function closeSupplierModal(){const modal=document.getElementById('supplier-create-modal');if(!modal)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true')}
function updateSupplierFields(){const type=document.getElementById('supplier-company-type')?.value||'supplier';const fields=document.getElementById('supplier-profile-fields');const label=document.querySelector('.supplier-submit span');if(fields)fields.style.display=type==='supplier'?'block':'none';if(label)label.textContent=type==='supplier'?'Dodaj dostawcę':'Dodaj klienta'}
function fetchSupplierFromGus(){const nip=document.getElementById('supplier-nip').value.replace(/[^0-9]/g,'');const status=document.getElementById('supplier-gus-status');if(nip.length!==10){status.style.color='#b91c1c';status.textContent='NIP musi mieć 10 cyfr.';return}status.style.color='#7b8580';status.textContent='Pobieranie danych z GUS...';fetch('{{route('companies.fetchGus')}}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({nip})}).then(async response=>{const data=await response.json();if(!response.ok)throw new Error(data.error||'Nie udało się pobrać danych z GUS.');return data}).then(data=>{document.getElementById('supplier-company-name').value=data.name||'';document.getElementById('supplier-company-address').value=data.address||'';document.getElementById('supplier-company-city').value=data.city||'';status.style.color='#2e7d32';status.textContent='Dane pobrane poprawnie.'}).catch(error=>{status.style.color='#b91c1c';status.textContent=error.message})}
document.getElementById('supplier-create-modal')?.addEventListener('click',event=>{if(event.target.id==='supplier-create-modal')closeSupplierModal()});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeSupplierModal()});
@if($canCreateSupplier && $errors->any()) openSupplierModal(); @endif
</script>
@endsection
