<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\BoardTask;
use App\Models\Project;
use App\Services\BoardAccessService;
use App\Services\BoardTaskService;
use App\Services\PersonalBoardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BoardController extends Controller
{
    public function index(Request $request, BoardAccessService $access)
    {
        return view('boards.mine', app(PersonalBoardService::class)->data($request));
    }

    public function store(Request $request, string $type, int $id, BoardAccessService $access, BoardTaskService $service)
    {
        $owner = $this->owner($type, $id);
        abort_unless($access->manage($request->user(), $owner), 403);
        $card = $service->save($owner, $this->validated($request), $request->user());

        return response()->json(['id' => $card->id], 201);
    }

    public function update(Request $request, BoardTask $card, BoardAccessService $access, BoardTaskService $service)
    {
        $owner = $card->project ?? $card->audit;
        abort_unless($owner && $access->manage($request->user(), $owner), 403);
        $service->save($owner, $this->validated($request, true), $request->user(), $card);

        return response()->json(['saved' => true]);
    }

    public function status(Request $request, BoardTask $card, BoardAccessService $access)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['todo', 'in_progress', 'done'])], 'revision' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($request, $card, $access, $data) {
            $card = BoardTask::lockForUpdate()->findOrFail($card->id);
            abort_unless($access->changeStatus($request->user(), $card), 403);
            abort_unless($card->revision === (int) $data['revision'], 409, 'Zadanie zmieniło się w międzyczasie. Odśwież tablicę.');
            $card->update(['status' => $data['status'], 'revision' => $card->revision + 1]);

            return response()->json(['revision' => $card->revision, 'color' => $card->color()]);
        });
    }

    public function destroy(Request $request, BoardTask $card, BoardAccessService $access)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $card, $access, $data) {
            $card = BoardTask::lockForUpdate()->findOrFail($card->id);
            $owner = $card->project ?? $card->audit;
            abort_unless($owner && $access->manage($request->user(), $owner), 403);
            abort_unless($card->revision === (int) $data['revision'], 409, 'Zadanie zmieniło się w międzyczasie. Odśwież tablicę.');
            $card->delete();
        });

        return response()->json(['deleted' => true]);
    }

    private function owner(string $type, int $id): Project|Audit
    {
        abort_unless(in_array($type, ['project', 'audit'], true), 404);

        return $type === 'project' ? Project::findOrFail($id) : Audit::findOrFail($id);
    }

    private function validated(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(['todo', 'in_progress', 'done'])],
            'stage_task_id' => ['nullable', 'integer'], 'assigned_to' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'revision' => [$editing ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
