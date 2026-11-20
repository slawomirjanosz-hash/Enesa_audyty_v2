<div class="audit-header-actions">
    @if(!$clientView && $isIso50001)
    <button type="button" id="audit-back-to-surveys" class="aw-btn soft audit-back-button" @if(request('tab')!=='iso50001') hidden @endif onclick="showAuditTab('surveys')"><i class="ti ti-arrow-left" aria-hidden="true"></i> Wróć do listy audytów</button>
    @endif
    @if(!$clientView && $canManage)
    <button type="button" class="aw-btn soft" onclick="openAwModal('audit-edit-modal')"><i class="ti ti-edit" aria-hidden="true"></i> Edytuj audyt</button>
    <button type="button" class="aw-btn soft audit-icon-button" title="Kopiuj audyt" aria-label="Kopiuj audyt" onclick="openAwModal('audit-copy-modal')"><i class="ti ti-copy" aria-hidden="true"></i></button>
    @endif
    @if(!$clientView && auth()->user()->hasRole('superadmin'))
    <button type="button" class="aw-btn audit-icon-button audit-delete-button" title="Usuń audyt" aria-label="Usuń audyt" onclick="openAwModal('audit-delete-modal')"><i class="ti ti-trash" aria-hidden="true"></i></button>
    @endif
</div>
