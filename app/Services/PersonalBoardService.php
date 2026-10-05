<?php

namespace App\Services;

use App\Models\BoardTask;
use App\Models\CompanySettings;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonalBoardService
{
    public function crm(User $user): Builder
    {
        $query = Task::crm();
        $full = app(AuditorAccessService::class)->hasFullAccess($user);
        if (! CompanySettings::moduleIsEnabled('crm') || (! $full && ! $user->canAny(['crm.view', 'crm.tasks.own.manage', 'crm.tasks.team.manage']))) {
            return $query->whereRaw('1=0');
        }
        if ($user->hasRole('auditor') && ! $full) {
            $query->where(function ($q) use ($user) {
                $q->whereIn('company_id', app(AuditorAccessService::class)->accessibleCompanyIds($user, 'can_view_dashboard'))
                    ->orWhere('assigned_to', $user->id)->orWhereHas('participants', fn ($p) => $p->whereKey($user->id))
                    ->orWhereHas('crmOpportunity', fn ($o) => $o->where('assigned_to', $user->id)->orWhere('created_by', $user->id)->orWhereHas('relatedUsers', fn ($u) => $u->whereKey($user->id)));
            });
        }

        return $query;
    }

    public function data(Request $request): array
    {
        $user = $request->user();
        $team = $user->can('board.team.view');
        $base = app(BoardAccessService::class)->visible($user);
        $crmBase = $this->crm($user);
        $archivedBoard = $request->boolean('archive');
        if ($archivedBoard) {
            $base->onlyTrashed();
            $crmBase->onlyTrashed();
        }
        $modules = collect(['projects' => 'Projekty', 'audits' => 'Audyty', 'crm' => 'CRM'])->filter(fn ($label, $key) => CompanySettings::moduleIsEnabled($key))->all();
        $options = [];
        foreach (['projects' => 'project', 'audits' => 'audit'] as $key => $relation) {
            if (isset($modules[$key])) {
                $options[$key] = (clone $base)->whereNotNull($relation.'_id')->select($relation.'_id')->distinct()->with($relation)->get()->mapWithKeys(fn ($row) => [$row->{$relation.'_id'} => $row->$relation->number.' · '.($row->$relation->name ?? $row->$relation->title)])->sort()->all();
            }
        }
        if (isset($modules['crm'])) {
            $options['crm'] = ['tasks' => 'Zadania CRM i szans sprzedaży'];
        }
        // Only expose names attached to an accessible task, not a directory of all users.
        $people = collect([$user]);
        if ($team) {
            $ids = (clone $base)->whereNotNull('assigned_to')->pluck('assigned_to')->merge((clone $crmBase)->whereNotNull('assigned_to')->pluck('assigned_to'));
            $ids = $ids->merge(DB::table('board_task_participants')->whereIn('board_task_id', (clone $base)->select('id'))->pluck('user_id'))
                ->merge(DB::table('task_participants')->whereIn('task_id', (clone $crmBase)->select('id'))->pluck('user_id'));
            $people = User::whereIn('id', $ids->push($user->id)->unique())->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['client_admin', 'client_user']))->orderBy('name')->get();
            $options['users'] = $people->mapWithKeys(fn ($p) => [$p->id => $p->id === $user->id ? 'Moje zadania' : $p->name])->all();
        }
        $request->validate(['projects' => 'sometimes|array|max:500', 'audits' => 'sometimes|array|max:500', 'crm' => 'sometimes|array|max:2', 'users' => 'sometimes|array|max:500', 'group' => 'sometimes|string|max:100']);
        abort_if(! $team && $request->has('users'), 403);
        $selected = [];
        foreach ($options as $key => $values) {
            $default = $key === 'users' ? [(string) $user->id] : array_map('strval', array_keys($values));
            $input = $request->input($key, $default);
            abort_if(collect($input)->contains(fn ($v) => $v !== null && ! is_scalar($v)), 422);
            $selected[$key] = array_values(array_unique(array_map('strval', array_filter($input, fn ($v) => $v !== '' && $v !== null))));
            abort_if(array_diff($selected[$key], array_map('strval', array_keys($values))), 403);
        }
        if ($request->filled('group')) {
            [$kind, $id] = array_pad(explode('-', $request->input('group'), 2), 2, '');
            $key = ['project' => 'projects', 'audit' => 'audits'][$kind] ?? '';
            abort_unless(isset($options[$key][$id]), 404);
            foreach (['projects', 'audits', 'crm'] as $group) {
                $selected[$group] = $key === $group ? [$id] : [];
            }
        }
        $userIds = $selected['users'] ?? [$user->id];
        $assigned = fn ($q) => $q->whereIn('assigned_to', $userIds)->orWhereHas('participants', fn ($p) => $p->whereKey($userIds));
        $base->where($assigned)->where(fn ($q) => $q->whereIn('project_id', $selected['projects'] ?? [])->orWhereIn('audit_id', $selected['audits'] ?? []));
        $crmBase->where($assigned)->when(! in_array('tasks', $selected['crm'] ?? []), fn ($q) => $q->whereRaw('1=0'));
        // Union identifiers first: filters and access restrictions apply before shared pagination.
        $union = $base->selectRaw("id, due_date, 'board' AS kind")->toBase()->unionAll($crmBase->selectRaw("id, due_date, 'crm' AS kind")->toBase());
        $cards = DB::query()->fromSub($union, 'board_rows')->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('kind')->orderBy('id')->paginate(90)->withQueryString();
        $boardRows = BoardTask::withTrashed()->with(['project', 'audit', 'stage', 'assignee', 'participants'])->whereIn('id', $cards->getCollection()->where('kind', 'board')->pluck('id'))->get()->keyBy('id');
        $crmRows = Task::withTrashed()->with(['company', 'crmOpportunity', 'assignedUser', 'participants'])->whereIn('id', $cards->getCollection()->where('kind', 'crm')->pluck('id'))->get()->keyBy('id');
        $cards->setCollection($cards->getCollection()->map(function ($row) use ($boardRows, $crmRows) {
            if ($row->kind === 'board') {
                return $boardRows[$row->id];
            }
            $task = $crmRows[$row->id];
            $card = new BoardTask($task->only(['title', 'description', 'assigned_to', 'status', 'due_date']));
            $card->id = $task->id;
            $card->revision = $task->board_revision;
            $card->deleted_at = $task->deleted_at;
            foreach (['project' => null, 'audit' => null, 'stage' => null, 'assignee' => $task->assignedUser, 'participants' => $task->participants, 'crmTask' => $task] as $key => $value) {
                $card->setRelation($key, $value);
            }

            return $card;
        }));

        return compact('cards', 'modules', 'options', 'selected', 'team', 'archivedBoard');
    }
}
