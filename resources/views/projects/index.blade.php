@extends('layouts.app')

@section('page-title', 'Projekty')

@section('content')
<style>
    .project-overdue-alert{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#FEE2E2;color:#B91C1C;font-size:16px;font-weight:900;margin-left:7px;vertical-align:middle}
    .project-head{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:24px}.btn{border:0;border-radius:8px;padding:10px 16px;background:var(--green);color:#fff;font-weight:700;cursor:pointer;text-decoration:none}.filters{display:flex;gap:10px;margin-bottom:18px}.filters select{padding:9px 12px;border:1px solid #d8d4ca;border-radius:8px;background:#fff}.project-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:16px}.project-card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:20px;text-decoration:none;color:inherit;transition:.15s}.project-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(20,45,35,.08)}.project-number{font-size:11px;color:#6b7a70;font-weight:800;letter-spacing:.08em}.project-card h2{font-size:17px;margin:7px 0 12px}.meta{display:grid;gap:7px;font-size:13px;color:#59665e}.status{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:800;background:#eef4ef;color:#24543d}.money{font-size:16px;font-weight:800;color:var(--green);margin-top:14px}.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.46);z-index:2000;align-items:center;justify-content:center;padding:16px}.modal.open{display:flex}.modal-box{background:#fff;border-radius:14px;padding:20px;width:min(1120px,100%);max-height:calc(100vh - 32px);overflow:auto}.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px 14px}.field{display:flex;flex-direction:column;gap:5px;min-width:0}.field.half{grid-column:span 2}.field.full{grid-column:1/-1}.field label{font-size:12px;font-weight:800}.field input,.field select,.field textarea{width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid #d5d0c5;border-radius:7px;font:inherit}.member-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px 12px;padding:10px;border:1px solid #d5d0c5;border-radius:8px;background:#fafaf7}.member-check{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;min-width:0}.member-check input{width:16px;height:16px;flex:0 0 auto}.member-check span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.empty{padding:45px;text-align:center;background:#fff;border:1px dashed #ccc;border-radius:12px;color:#777}@media(max-width:900px){.form-grid{grid-template-columns:1fr 1fr}.field.half{grid-column:span 1}.member-checks{grid-template-columns:1fr 1fr}}@media(max-width:650px){.form-grid{grid-template-columns:1fr}.field.half,.field.full{grid-column:auto}.member-checks{grid-template-columns:1fr}}
</style>

<div class="project-head">
    <div><h1 style="margin:0 0 5px;">Projekty</h1><p style="margin:0;color:#6b7a70;">Harmonogramy, finanse, zadania, zakupy i dokumenty w jednym miejscu.</p></div>
    @can('projects.create')<button class="btn" type="button" onclick="document.getElementById('project-modal').classList.add('open')"><i class="ti ti-plus"></i> Nowy projekt</button>@endcan
</div>

@if(session('success'))<div style="padding:12px 15px;background:#ecfdf5;color:#166534;border-radius:8px;margin-bottom:16px;">{{ session('success') }}</div>@endif

<form class="filters" method="GET">
    <select name="status" onchange="this.form.submit()">
        <option value="">Wszystkie statusy</option>
        @foreach(['planned'=>'Planowany','active'=>'Aktywny','on_hold'=>'Wstrzymany','completed'=>'Zakończony','cancelled'=>'Anulowany'] as $value=>$label)
            <option value="{{ $value }}" {{ request('status')===$value?'selected':'' }}>{{ $label }}</option>
        @endforeach
    </select>
</form>

@if($projects->isEmpty())
    <div class="empty"><i class="ti ti-briefcase-off" style="font-size:36px;"></i><p>Nie ma jeszcze żadnych projektów.</p></div>
@else
<div class="project-grid">
    @foreach($projects as $project)
    <a class="project-card" href="{{ route('projects.show',$project) }}">
        <div style="display:flex;justify-content:space-between;gap:10px;"><span class="project-number">{{ $project->number }} @if($project->overdue_tasks_count > 0)<span class="project-overdue-alert" title="{{ $project->overdue_tasks_count }} zadań po terminie">!</span>@endif</span><span class="status">{{ ['planned'=>'Planowany','active'=>'Aktywny','on_hold'=>'Wstrzymany','completed'=>'Zakończony','cancelled'=>'Anulowany'][$project->status] ?? $project->status }}</span></div>
        <h2>{{ $project->name }}</h2>
        <div class="meta">
            <span><i class="ti ti-building"></i> {{ $project->company?->name ?? 'Projekt wewnętrzny' }}</span>
            <span><i class="ti ti-user-star"></i> {{ $project->manager?->name ?? 'Brak kierownika' }}</span>
            <span><i class="ti ti-users"></i> Zespół: {{ $project->members->count() }}</span>
            <span><i class="ti ti-calendar"></i> {{ $project->start_date?->format('d.m.Y') ?? '—' }} – {{ $project->end_date?->format('d.m.Y') ?? '—' }}</span>
        </div>
        @if($canViewFinances)<div class="money">{{ number_format((float)$project->contract_value,2,',',' ') }} zł</div>@endif
    </a>
    @endforeach
</div>
<div style="margin-top:20px;">{{ $projects->links() }}</div>
@endif

<div id="project-modal" class="modal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;"><h2 style="margin:0;">Nowy projekt</h2><button type="button" onclick="document.getElementById('project-modal').classList.remove('open')" style="border:0;background:none;font-size:24px;cursor:pointer;">×</button></div>
        <form method="POST" action="{{ route('projects.store') }}">@csrf
            <div class="form-grid">
                <div class="field"><label>Numer projektu *</label><input name="number" value="{{ old('number') }}" required placeholder="PRJ/2026/001"></div>
                <div class="field"><label>Nazwa projektu *</label><input name="name" value="{{ old('name') }}" required></div>
                <div class="field"><label>Firma / klient</label><select name="company_id"><option value="">Projekt wewnętrzny</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                <div class="field"><label>Kierownik projektu *</label><select name="manager_id" required><option value="">Wybierz</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                <div class="field"><label>Status</label><select name="status"><option value="planned">Planowany</option><option value="active">Aktywny</option><option value="on_hold">Wstrzymany</option><option value="completed">Zakończony</option></select></div>
                @if($canViewFinances)<div class="field"><label>Wartość kontraktu netto</label><input type="number" step="0.01" min="0" name="contract_value" value="0" required></div>@endif
                <div class="field"><label>Data rozpoczęcia</label><input type="date" name="start_date"></div><div class="field"><label>Data zakończenia</label><input type="date" name="end_date"></div>
                <div class="field half"><label>Kto może widzieć projekt</label><div class="member-checks">@foreach($users as $user)<label class="member-check"><input type="checkbox" name="member_ids[]" value="{{ $user->id }}" {{ in_array($user->id, old('member_ids', [])) ? 'checked' : '' }}><span>{{ $user->name }}</span></label>@endforeach</div><small>Administratorzy widzą wszystkie projekty. Pozostali użytkownicy zobaczą tylko projekty, do których zostali tutaj przypisani.</small></div>
                <div class="field half"><label>Opis</label><textarea name="description" rows="4">{{ old('description') }}</textarea></div>
            </div>
            <div style="margin-top:18px;text-align:right;"><button class="btn">Utwórz projekt</button></div>
        </form>
    </div>
</div>
@if($errors->any())<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('project-modal').classList.add('open'));</script>@endif
@endsection
