<div class="audit-header-actions">
    @if(!$clientView && $canManage)
    <button type="button" class="aw-btn soft" onclick="openAwModal('audit-copy-modal')"><i class="ti ti-copy"></i> Kopiuj audyt</button>
    <button type="button" class="aw-btn soft" onclick="openAwModal('audit-edit-modal')"><i class="ti ti-edit"></i> Edytuj audyt</button>
    @endif
    @if(!$clientView && auth()->user()->hasAnyRole(['admin', 'superadmin']))
    <button type="button" class="aw-btn audit-delete-button" onclick="openAwModal('audit-delete-modal')"><i class="ti ti-trash"></i> Usuń audyt</button>
    @endif
    <span class="aw-badge">{{ $statusLabels[$audit->status] ?? $audit->status }}</span>
</div>
