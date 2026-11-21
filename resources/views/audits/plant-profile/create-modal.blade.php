@include('audits.partials.create-dialog-styles')
<button type="button" class="iso-action-btn primary" data-plant-create-open onclick="document.getElementById('plant-create-dialog').showModal()">Dodaj profil zakładu</button>
<dialog id="plant-create-dialog" class="audit-create-dialog" style="width:min(520px,calc(100vw - 32px))" aria-labelledby="plant-create-title">
    <div style="display:flex;justify-content:space-between;align-items:center"><h2 id="plant-create-title">Dodaj profil zakładu</h2><button type="button" aria-label="Zamknij" onclick="this.closest('dialog').close()" style="border:0;background:none;font-size:24px;cursor:pointer">×</button></div>
    @if($errors->plantCreate->any())<div role="alert" style="color:#b91c1c">@foreach($errors->plantCreate->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="post" action="{{ route($profilePrefix.'create',$audit) }}">@csrf
        <input type="hidden" name="return_to" value="overview">
        <div class="audit-create-fields">
            <div class="audit-create-field full"><label for="plant-create-name">Nazwa zakładu</label><input id="plant-create-name" name="name" value="{{ $errors->plantCreate->any()?old('name'):'' }}" required maxlength="200" autofocus @if($errors->plantCreate->has('name')) aria-invalid="true" style="border-color:#b91c1c" @endif></div>
            <div class="audit-create-field full"><label for="plant-create-person">Osoba odpowiedzialna</label><select id="plant-create-person" name="responsible_user_id" @if($errors->plantCreate->has('responsible_user_id')) aria-invalid="true" style="border-color:#b91c1c" @endif><option value="">Nieprzypisana</option>@foreach($profileUsers as $person)<option value="{{ $person->id }}" @selected($errors->plantCreate->any() && old('responsible_user_id') == $person->id)>{{ $person->name }}</option>@endforeach</select></div>
        </div>
        <div class="audit-create-actions"><button type="button" class="audit-create-button" style="background:#edf3ef;color:var(--green,var(--brand,#1a4d3a))" onclick="this.closest('dialog').close()">Anuluj</button><button type="submit" class="audit-create-button">Zapisz</button></div>
    </form>
</dialog>
@if($errors->plantCreate->any() || request()->boolean('add_profile'))<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('plant-create-dialog').showModal());</script>@endif
