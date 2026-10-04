<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BoardTask;
use App\Models\Task;
use App\Models\User;
use App\Services\BoardAccessService;
use App\Services\PersonalBoardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BoardCollaborationController extends Controller
{
    public static function candidates(BoardTask|Task $card)
    {
        if ($card instanceof BoardTask) {
            $owner = $card->project ?? $card->audit;

            return collect([$owner->manager])->filter()->merge($owner->members)->unique('id')
                ->filter(fn ($u) => $u->is_active && ! $u->hasAnyRole(['client_admin', 'client_user']) && app(BoardAccessService::class)->view($u, $owner));
        }

        return User::where('is_active', true)->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['client_admin', 'client_user']))->with(['roles.permissions', 'permissions'])->orderBy('name')->get()
            ->filter(fn ($u) => $u->hasRole('superadmin') || $u->canAny(['system.full_access', 'crm.view', 'crm.tasks.own.manage', 'crm.tasks.team.manage']));
    }

    public function participants(Request $request, string $kind, int $id)
    {
        abort_unless(in_array($kind, ['board', 'crm']), 404);
        $data = $request->validate(['participants' => 'present|array|max:100', 'participants.*' => 'integer|distinct', 'revision' => 'required|integer|min:1']);

        return DB::transaction(function () use ($request, $kind, $id, $data) {
            $card = $kind === 'board' ? BoardTask::lockForUpdate()->findOrFail($id) : app(PersonalBoardService::class)->crm($request->user())->lockForUpdate()->findOrFail($id);
            abort_unless($kind === 'board' ? app(BoardAccessService::class)->changeStatus($request->user(), $card) : Gate::allows('update', $card), 403);
            $revision = $kind === 'board' ? 'revision' : 'board_revision';
            abort_unless((int) $card->$revision === (int) $data['revision'], 409, 'Zadanie zmieniło się. Odśwież tablicę.');
            abort_if(array_diff($data['participants'], self::candidates($card)->pluck('id')->all()), 422, 'Wybierz aktywne osoby mające dostęp do tego modułu i zespołu.');
            $before = $card->participants()->pluck('users.id')->all();
            $card->participants()->sync(array_values(array_diff($data['participants'], [$card->assigned_to])));
            if ($kind === 'board') {
                $card->revision++;
            } else {
                $card->board_revision = (int) $card->board_revision + 1;
            }
            $card->touch();
            ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'updated', 'auditable_type' => $card::class, 'auditable_id' => $card->id, 'subject_label' => 'Uczestnicy zadania: '.$card->title, 'changes' => ['participants' => ['old' => $before, 'new' => $data['participants']]]]);

            return response()->json(['saved' => true]);
        });
    }

    public function crmStatus(Request $request, Task $task)
    {
        abort_unless(app(PersonalBoardService::class)->crm($request->user())->whereKey($task->id)->exists(), 404);
        $this->authorize('update', $task);
        $data = $request->validate(['status' => ['required', Rule::in(['todo', 'in_progress', 'done'])], 'revision' => 'required|integer|min:1']);

        return DB::transaction(function () use ($task, $data) {
            $task = Task::lockForUpdate()->findOrFail($task->id);
            $this->authorize('update', $task);
            abort_unless((int) $task->board_revision === (int) $data['revision'], 409, 'Zadanie zmieniło się. Odśwież tablicę.');
            $task->update(['status' => $data['status']]);
            $color = $task->status === 'done' ? 'green' : ($task->due_date?->lt(today()) ? 'red' : ($task->status === 'in_progress' ? 'orange' : 'gray'));

            return response()->json(['revision' => $task->board_revision, 'color' => $color]);
        });
    }
}
