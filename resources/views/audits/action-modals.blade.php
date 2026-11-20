@if(!$clientView && $canManage)
<div id="audit-copy-modal" class="aw-modal" role="dialog" aria-modal="true" aria-labelledby="audit-copy-title"><div class="aw-modal-box">
    <h2 id="audit-copy-title">Kopiuj audyt</h2>
    <p>Czy na pewno chcesz utworzyć kopię audytu „{{ $audit->number }} — {{ $audit->title }}”?</p>
    <p>Kopia zachowa firmę, kierownika, zespół, opis, wartość kontraktu i terminy. Harmonogram zostanie skopiowany z wyzerowanym postępem, a ankiety tych samych typów będą puste. Odpowiedzi, zatwierdzenia, dokumenty, paszporty i wpisy finansowe nie są kopiowane.</p>
    @foreach($errors->auditCopy->all() as $error)<p style="color:#b91c1c">{{ $error }}</p>@endforeach
    <form method="POST" action="{{ route('audits.copy', $audit) }}">@csrf
        <div class="aw-grid">
            <div class="aw-field wide"><label for="audit-copy-number">Nowy numer audytu</label><input id="audit-copy-number" class="aw-input" name="number" maxlength="80" value="{{ $errors->auditCopy->any() ? old('number') : '' }}" required></div>
            <div class="aw-field wide"><label for="audit-copy-name">Nazwa audytu</label><input id="audit-copy-name" class="aw-input" name="title" maxlength="255" value="{{ $errors->auditCopy->any() ? old('title') : $audit->title }}" required></div>
        </div>
        <label class="audit-action-confirm"><input type="checkbox" name="confirm_copy" value="1" required> Potwierdzam utworzenie kopii tego audytu.</label>
        <div class="aw-actions" style="margin-top:16px"><button type="button" class="aw-btn soft" onclick="closeAwModal('audit-copy-modal')">Anuluj</button><button class="aw-btn">Tak, utwórz kopię</button></div>
    </form>
</div></div>
@if($errors->auditCopy->any())<script>document.addEventListener('DOMContentLoaded',()=>openAwModal('audit-copy-modal'));</script>@endif
@endif
@if(!$clientView && auth()->user()->hasRole('superadmin'))
<div id="audit-delete-modal" class="aw-modal" role="dialog" aria-modal="true" aria-labelledby="audit-delete-title"><div class="aw-modal-box">
    <h2 id="audit-delete-title">Usuń audyt</h2>
    <p>Audyt „{{ $audit->title }}” i jego zadania zostaną usunięte z aktywnych list. Dane i pliki pozostaną zachowane.</p>
    <form method="POST" action="{{ route('audits.destroy', $audit) }}">@csrf @method('DELETE')
        <label class="audit-action-confirm"><input type="checkbox" name="confirm_delete" value="1" required> Potwierdzam usunięcie tego audytu.</label>
        <div class="aw-actions" style="margin-top:16px"><button type="button" class="aw-btn soft" onclick="closeAwModal('audit-delete-modal')">Anuluj</button><button class="aw-btn red">Usuń audyt</button></div>
    </form>
</div></div>
@endif
