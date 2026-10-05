<?php

namespace App\Http\Controllers;

use App\Models\BoardTask;
use App\Services\BoardAccessService;
use App\Services\PersonalBoardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskArchiveController extends Controller
{
    public function update(Request $request, string $kind, int $id)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'restore' => ['required', 'boolean']]);
        abort_unless(in_array($kind, ['board', 'crm'], true), 404);

        return DB::transaction(function () use ($request, $kind, $id, $data) {
            $query = $kind === 'crm' ? app(PersonalBoardService::class)->crm($request->user()) : BoardTask::query();
            if ($data['restore']) {
                $query->onlyTrashed();
            }
            $task = $query->lockForUpdate()->findOrFail($id);
            abort_if($request->user()->hasAnyRole(['client_admin', 'client_user']), 403);
            if ($kind === 'board') {
                abort_unless(app(BoardAccessService::class)->changeStatus($request->user(), $task), 403);
            } else {
                $this->authorize('update', $task);
            }
            $field = $kind === 'board' ? 'revision' : 'board_revision';
            abort_unless((int) $task->$field === (int) $data['revision'], 409, 'Zadanie zmieniło się. Odśwież stronę.');
            if ($kind === 'board') {
                $task->update(['revision' => $task->revision + 1]);
            } else {
                $task->update(['deleted_by' => $data['restore'] ? null : $request->user()->id]);
            }
            $data['restore'] ? $task->restore() : $task->delete();

            return response()->json(['saved' => true]);
        });
    }
}
