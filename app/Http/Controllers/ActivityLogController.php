<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->string('tab')->value() === 'logins' ? 'logins' : 'changes';
        $requestedMonth = $request->string('month')->value();
        $monthNumber = (int) substr($requestedMonth, 5, 2);
        $month = preg_match('/^\d{4}-\d{2}$/', $requestedMonth) && $monthNumber >= 1 && $monthNumber <= 12
            ? $requestedMonth : null;
        $query = ActivityLog::with('user')->orderByDesc('created_at');
        $tab === 'logins'
            ? $query->whereIn('action', ['login', 'logout'])
            : $query->whereNotIn('action', ['login', 'logout']);

        if ($month) {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $query->whereBetween('created_at', [$start, $start->copy()->endOfMonth()]);
        }

        $months = ActivityLog::query()
            ->when($tab === 'logins', fn ($logs) => $logs->whereIn('action', ['login', 'logout']))
            ->when($tab !== 'logins', fn ($logs) => $logs->whereNotIn('action', ['login', 'logout']))
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->unique()
            ->values();

        return view('activity-log.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'months' => $months,
            'selectedMonth' => $month,
            'tab' => $tab,
        ]);
    }
}
