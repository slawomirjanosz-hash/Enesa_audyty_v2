<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
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
        $tasksByDate = $tasks->groupBy(fn (Task $task) => $task->due_date->format('Y-m-d'));
        $days = collect(range(0, (int) $gridStart->diffInDays($gridEnd)))
            ->map(fn (int $offset) => $gridStart->addDays($offset));

        $users = $canViewTeam
            ? User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($roles) => $roles->whereNotIn('name', ['client_admin', 'client_user']))
                ->orderBy('name')
                ->get()
            : collect([$user]);

        $monthTasks = $tasks->filter(fn (Task $task) => $task->due_date->betweenIncluded($month, $month->endOfMonth()));
        $stats = [
            'month' => $monthTasks->count(),
            'open' => $monthTasks->where('status', '!=', 'done')->count(),
            'overdue' => $monthTasks->filter(fn (Task $task) => $task->status !== 'done' && $task->due_date->isBefore(today()))->count(),
            'done' => $monthTasks->where('status', 'done')->count(),
        ];

        return view('calendar.index', compact(
            'month', 'gridStart', 'gridEnd', 'days', 'tasksByDate', 'users',
            'scope', 'selectedUserId', 'canViewTeam', 'stats'
        ));
    }
}
