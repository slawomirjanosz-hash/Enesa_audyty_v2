<?php

namespace App\Http\Controllers;

use App\Models\BoardTask;
use App\Models\CompanySettings;
use App\Models\HrBusinessTrip;
use App\Models\HrLeave;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
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
        $card = new BoardTask;
        $card->forceFill($data + ['assigned_to' => $request->user()->id, 'created_by' => $request->user()->id, 'status' => 'todo', 'revision' => 1]);
        $card->save();

        return redirect()->route('calendar.index', ['month' => substr($data['due_date'], 0, 7)])
            ->with('success', 'Zapisano wpis w kalendarzu i na Mojej tablicy.');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewTeam = $user->hasRole('superadmin')
            || $user->can('system.full_access')
            || $user->can('calendar.team.view');

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'scope' => ['nullable', 'in:mine,team'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
        $gridStart = $month->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $scope = $canViewTeam && ($validated['scope'] ?? 'mine') === 'team' ? 'team' : 'mine';
        $selectedUserId = $scope === 'team' && isset($validated['user_id'])
            ? (int) $validated['user_id']
            : null;

        $tasksQuery = Task::query()
            ->with(['assignedUser', 'company', 'project', 'crmOpportunity'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('title');

        $crmEnabled = CompanySettings::moduleIsEnabled('crm');
        $projectsEnabled = CompanySettings::moduleIsEnabled('projects');
        if (! $crmEnabled && ! $projectsEnabled) {
            $tasksQuery->whereRaw('1 = 0');
        } elseif (! $crmEnabled) {
            $tasksQuery->whereNotNull('project_id');
        } elseif (! $projectsEnabled) {
            $tasksQuery->whereNull('project_id');
        }

        if ($scope === 'mine') {
            $tasksQuery->where('assigned_to', $user->id);
        } elseif ($selectedUserId) {
            $tasksQuery->where('assigned_to', $selectedUserId);
        }

        $tasks = $tasksQuery->get();
        $personalTasks = BoardTask::calendarEntries()->with('assignee')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when($scope === 'mine', fn ($q) => $q->where('assigned_to', $user->id))
            ->when($selectedUserId, fn ($q) => $q->where('assigned_to', $selectedUserId))
            ->orderBy('scheduled_time')->orderBy('id')->get();
        $personalByDate = $personalTasks->groupBy(fn ($task) => $task->due_date->format('Y-m-d'));
        $tasksByDate = $tasks->groupBy(fn (Task $task) => $task->due_date->format('Y-m-d'));
        $hrEventsByDate = collect();
        $hrEnabled = CompanySettings::moduleIsEnabled('hr');
        $canViewHrTeam = $user->hasRole(['superadmin', 'admin'])
            || $user->can('system.full_access')
            || $user->can('hr.team.view');
        $hrUserId = $scope === 'team' && $canViewHrTeam ? $selectedUserId : $user->id;
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
                ->when($hrUserId, fn ($query) => $query->where('user_id', $hrUserId))->get();
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
                ->when($hrUserId, fn ($query) => $query->where('user_id', $hrUserId))->get();
            foreach ($leaves as $leave) {
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

        $users = $canViewTeam
            ? User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($roles) => $roles->whereNotIn('name', ['client_admin', 'client_user']))
                ->orderBy('name')
                ->get()
            : collect([$user]);

        $monthTasks = $tasks->toBase()->concat($personalTasks)->filter(fn ($task) => $task->due_date->betweenIncluded($month, $month->endOfMonth()));
        $stats = [
            'month' => $monthTasks->count(),
            'open' => $monthTasks->where('status', '!=', 'done')->count(),
            'overdue' => $monthTasks->filter(fn ($task) => $task->status !== 'done' && $task->due_date->isBefore(today()))->count(),
            'done' => $monthTasks->where('status', 'done')->count(),
        ];

        return view('calendar.index', compact(
            'month', 'gridStart', 'gridEnd', 'days', 'tasksByDate', 'hrEventsByDate', 'users',
            'scope', 'selectedUserId', 'canViewTeam', 'stats', 'personalByDate'
        ));
    }
}
