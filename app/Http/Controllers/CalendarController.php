<?php

namespace App\Http\Controllers;

use App\Models\BoardTask;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\CrmOpportunity;
use App\Models\HrBusinessTrip;
use App\Models\HrLeave;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditorAccessService;
use App\Services\CrmTaskCreator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'calendar_kind' => ['required', 'in:task,meeting'],
        ]);
        if ($data['calendar_kind'] === 'task') {
            abort_unless(CompanySettings::moduleIsEnabled('crm'), 403);
            $request->mergeIfMissing(['status' => 'todo', 'priority' => 'medium', 'assigned_to' => $request->user()->id]);
            app(CrmTaskCreator::class)->create($request, 'default');
        } else {
            $card = new BoardTask;
            $card->forceFill($data + ['assigned_to' => $request->user()->id, 'created_by' => $request->user()->id, 'status' => 'todo', 'revision' => 1]);
            $card->save();
        }

        return redirect()->route('calendar.index', ['month' => substr($data['due_date'], 0, 7)])
            ->with('success', $data['calendar_kind'] === 'task' ? 'Zadanie zostało zapisane w kalendarzu, CRM i na Mojej tablicy.' : 'Zapisano spotkanie w kalendarzu i na Mojej tablicy.');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewTeam = $user->hasRole(['superadmin', 'admin'])
            || $user->can('system.full_access')
            || $user->can('calendar.team.view');

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'scope' => ['nullable', 'in:mine,team'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct'],
            'unassigned' => ['nullable', 'boolean'],
            'project_scope' => ['nullable', 'in:all,selected'],
            'project_ids' => ['nullable', 'array', 'max:500'],
            'project_ids.*' => ['integer', 'distinct'],
            'show_projects' => ['nullable', 'boolean'],
            'show_leaves' => ['nullable', 'boolean'],
            'show_sick_leave' => ['nullable', 'boolean'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
        $gridStart = $month->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $scope = $canViewTeam && ($validated['scope'] ?? 'mine') === 'team' ? 'team' : 'mine';
        $users = $canViewTeam
            ? User::where('is_active', true)->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', ['client_admin', 'client_user']))->orderBy('name')->get()
            : collect([$user]);
        $selectedUserIds = $scope === 'team'
            ? array_map('intval', $validated['user_ids'] ?? array_filter([$validated['user_id'] ?? null]))
            : [];
        if (array_diff($selectedUserIds, $users->pluck('id')->all())) {
            throw ValidationException::withMessages(['user_ids' => 'Wybierz osoby z listy zespołu.']);
        }
        $calendarUserIds = $scope === 'mine' ? [$user->id] : $selectedUserIds;
        $showUnassigned = $scope === 'team' && $request->boolean('unassigned');

        $tasksQuery = Task::query()
            ->with(['assignedUser', 'participants', 'company', 'project', 'crmOpportunity'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('title');

        $crmEnabled = CompanySettings::moduleIsEnabled('crm');
        $projectsEnabled = CompanySettings::moduleIsEnabled('projects');
        $calendarProjects = $projectsEnabled
            ? Project::orderBy('name')->get()->filter(fn ($project) => Gate::forUser($user)->allows('view', $project))
            : collect();
        $projectScope = $validated['project_scope'] ?? 'all';
        $selectedProjectIds = array_map('intval', $validated['project_ids'] ?? []);
        abort_if(array_diff($selectedProjectIds, $calendarProjects->pluck('id')->all()), 403);
        $showProjects = $request->boolean('show_projects', true);
        if ($showProjects) {
            foreach ($calendarProjects as $calendarProject) {
                app(\App\Services\ProjectScheduleDelayService::class)->syncProject($calendarProject->id);
            }
        }
        if (! $showProjects) {
            $tasksQuery->whereNull('project_id');
        } elseif ($projectScope === 'selected') {
            $tasksQuery->where(fn ($query) => $query->whereNull('project_id')->orWhereIn('project_id', $selectedProjectIds));
        }
        $showLeaves = $request->boolean('show_leaves', true);
        $showSickLeave = $request->boolean('show_sick_leave', true);
        $canViewCalendarLeaves = CompanySettings::moduleIsEnabled('hr') && ($user->hasRole('superadmin') || $user->canAny(['system.full_access', 'hr.leaves.view']));
        if (! $crmEnabled && ! $projectsEnabled) {
            $tasksQuery->whereRaw('1 = 0');
        } elseif (! $crmEnabled) {
            $tasksQuery->whereNotNull('project_id');
        } elseif (! $projectsEnabled) {
            $tasksQuery->whereNull('project_id');
        }

        $tasksQuery->where(function ($query) use ($calendarUserIds, $showUnassigned) {
            if ($calendarUserIds) {
                $query->whereIn('assigned_to', $calendarUserIds)
                    ->orWhereHas('participants', fn ($participants) => $participants->whereKey($calendarUserIds));
            } elseif (! $showUnassigned) {
                $query->whereNotNull('assigned_to')->orWhereHas('participants');
            }
            if ($showUnassigned) {
                $query->orWhere(fn ($task) => $task->whereNull('assigned_to')->whereDoesntHave('participants'));
            }
        });

        $tasks = $tasksQuery->get();
        $personalTasks = BoardTask::calendarEntries()->with('assignee')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->where(function ($query) use ($calendarUserIds, $showUnassigned) {
                if ($calendarUserIds) {
                    $query->whereIn('assigned_to', $calendarUserIds);
                } elseif (! $showUnassigned) {
                    $query->whereNotNull('assigned_to');
                }
                if ($showUnassigned) {
                    $query->orWhereNull('assigned_to');
                }
            })
            ->orderBy('scheduled_time')->orderBy('id')->get();
        $personalByDate = $personalTasks->groupBy(fn ($task) => $task->due_date->format('Y-m-d'));
        $tasksByDate = $tasks->groupBy(fn (Task $task) => $task->due_date->format('Y-m-d'));
        $hrEventsByDate = collect();
        $hrEnabled = CompanySettings::moduleIsEnabled('hr') && ! ($showUnassigned && ! $selectedUserIds);
        $canViewHrTeam = $user->hasRole(['superadmin', 'admin'])
            || $user->can('system.full_access')
            || $user->can('hr.team.view');
        $hrUserIds = $scope === 'team' && $canViewHrTeam ? $selectedUserIds : [$user->id];
        $addHrEvent = function (CarbonImmutable $date, array $event) use ($hrEventsByDate, $gridStart, $gridEnd): void {
            if ($date->betweenIncluded($gridStart, $gridEnd)) {
                $key = $date->toDateString();
                $hrEventsByDate->put($key, $hrEventsByDate->get($key, collect())->push($event));
            }
        };

        if ($hrEnabled && ($user->hasRole('superadmin') || $user->canAny(['system.full_access', 'hr.delegations.view']))) {
            $trips = HrBusinessTrip::with('user')
                ->where('departure_at', '<=', $gridEnd->endOfDay())
                ->where(fn ($query) => $query->whereNull('return_at')->orWhere('return_at', '>=', $gridStart->startOfDay()))
                ->when($hrUserIds, fn ($query) => $query->whereIn('user_id', $hrUserIds))->get();
            foreach ($trips as $trip) {
                $date = CarbonImmutable::parse($trip->departure_at)->startOfDay();
                $lastDate = CarbonImmutable::parse($trip->return_at ?? $trip->departure_at)->startOfDay();
                while ($date->lte($lastDate)) {
                    $addHrEvent($date, [
                        'title' => 'Delegacja: '.$trip->purpose,
                        'meta' => $trip->user?->name.' · '.$trip->origin.' → '.$trip->destination,
                        'url' => route('hr.delegations.show', $trip),
                        'class' => 'business-trip',
                    ]);
                    $date = $date->addDay();
                }
            }
        }

        if ($hrEnabled && ($user->hasRole('superadmin') || $user->canAny(['system.full_access', 'hr.leaves.view']))) {
            $leaves = HrLeave::with('user')->whereIn('approval_status', ['legacy', 'approved', 'recorded'])->whereDate('start_date', '<=', $gridEnd)
                ->whereDate('end_date', '>=', $gridStart)
                ->when($hrUserIds, fn ($query) => $query->whereIn('user_id', $hrUserIds))->get();
            foreach ($leaves as $leave) {
                if ($leave->type === 'sick_leave' ? ! $showSickLeave : ! $showLeaves) {
                    continue;
                }
                $date = CarbonImmutable::parse($leave->start_date);
                $lastDate = CarbonImmutable::parse($leave->end_date);
                while ($date->lte($lastDate)) {
                    $addHrEvent($date, [
                        'title' => HrLeave::TYPES[$leave->type] ?? 'Nieobecność',
                        'meta' => $leave->user?->name,
                        'url' => route('hr.index', ['tab' => 'leaves', 'user_id' => $canViewHrTeam ? $leave->user_id : null]),
                        'class' => $leave->type === 'sick_leave' ? 'sick-leave' : 'leave',
                    ]);
                    $date = $date->addDay();
                }
            }
        }
        $days = collect(range(0, (int) $gridStart->diffInDays($gridEnd)))
            ->map(fn (int $offset) => $gridStart->addDays($offset));

        $monthTasks = $tasks->toBase()->concat($personalTasks)->filter(fn ($task) => $task->due_date->betweenIncluded($month, $month->endOfMonth()));
        $stats = [
            'month' => $monthTasks->count(),
            'open' => $monthTasks->where('status', '!=', 'done')->count(),
            'overdue' => $monthTasks->filter(fn ($task) => $task->status !== 'done' && $task->due_date->isBefore(today()))->count(),
            'done' => $monthTasks->where('status', 'done')->count(),
        ];

        $canCreateCrmTasks = $crmEnabled && (app(AuditorAccessService::class)->hasFullAccess($user) || $user->canAny(['crm.tasks.own.manage', 'crm.tasks.team.manage']));
        $canAssignCrmTeam = $canCreateCrmTasks && (app(AuditorAccessService::class)->hasFullAccess($user) || $user->can('crm.tasks.team.manage'));
        $taskUsers = $canAssignCrmTeam ? User::where('is_active', true)->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', ['client_admin', 'client_user']))->orderBy('name')->get() : collect([$user]);
        $taskCompanies = $canCreateCrmTasks ? app(AuditorAccessService::class)->scopeByCompanyAccess(Company::clients()->where('status', '!=', 'archived')->orderBy('name'), $user, 'can_view_dashboard', 'id')->get() : collect();
        $taskOpportunities = $canCreateCrmTasks ? app(AuditorAccessService::class)->scopeByCompanyAccess(CrmOpportunity::orderBy('title'), $user, 'can_view_dashboard')->get() : collect();

        return view('calendar.index', compact(
            'month', 'gridStart', 'gridEnd', 'days', 'tasksByDate', 'hrEventsByDate', 'users',
            'scope', 'selectedUserIds', 'showUnassigned', 'canViewTeam', 'stats', 'personalByDate', 'canCreateCrmTasks', 'canAssignCrmTeam', 'taskUsers', 'taskCompanies', 'taskOpportunities',
            'showProjects', 'calendarProjects', 'projectsEnabled', 'projectScope', 'selectedProjectIds', 'showLeaves', 'showSickLeave', 'canViewCalendarLeaves'
        ));
    }
}
