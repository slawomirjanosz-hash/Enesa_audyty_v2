<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditorAccessService;
use App\Services\BoardAccessService;
use App\Services\PersonalBoardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskDeletionController extends Controller
{
    public static function administrator(User $user): bool
    {
        return ! $user->hasAnyRole(['client_admin', 'client_user'])
            && ($user->hasAnyRole(['admin', 'superadmin']) || app(AuditorAccessService::class)->hasFullAccess($user));
    }

    public function destroy(Request $request, string $kind, int $id)
    {
        abort_unless(self::administrator($request->user()), 403);
        abort_unless(in_array($kind, ['board', 'crm'], true), 404);
        $data = $request->validate(['revision' => 'required|integer|min:1']);

        DB::transaction(function () use ($request, $kind, $id, $data) {
            $query = $kind === 'crm'
                ? app(PersonalBoardService::class)->crm($request->user())
                : app(BoardAccessService::class)->visible($request->user());
            $task = $query->withTrashed()->lockForUpdate()->findOrFail($id);
            $revision = $kind === 'crm' ? 'board_revision' : 'revision';
            abort_unless((int) $task->$revision === (int) $data['revision'], 409, 'Zadanie zmieniło się. Odśwież stronę.');
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'deleted', 'auditable_type' => $task::class, 'auditable_id' => $task->id, 'subject_label' => 'Trwale usunięte zadanie: '.$task->title]);
            $task->participants()->detach();
            $task->forceDelete();
        });

        return $request->expectsJson() ? response()->json(['deleted' => true]) : back()->with('success', 'Zadanie zostało trwale usunięte.');
    }
}
