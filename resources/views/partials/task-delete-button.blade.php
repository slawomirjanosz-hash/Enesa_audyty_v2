@if(\App\Http\Controllers\TaskDeletionController::administrator(auth()->user()))
<form method="POST" action="{{ route('board.permanent-delete', [$deletionTask instanceof \App\Models\BoardTask ? 'board' : 'crm', $deletionTask->id]) }}" style="display:inline;" onsubmit="return confirm('Trwale usunąć to zadanie? Tej operacji nie można cofnąć.');">
    @csrf @method('DELETE')
    <input type="hidden" name="revision" value="{{ $deletionTask instanceof \App\Models\BoardTask ? $deletionTask->revision : $deletionTask->board_revision }}">
    <button type="submit" class="btn-icon" title="Usuń zadanie trwale" aria-label="Usuń zadanie trwale" style="color:#b91c1c;"><i class="ti ti-trash" aria-hidden="true"></i></button>
</form>
@endif
