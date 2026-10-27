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
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200']]);
        $search = trim($filters['search'] ?? '');
        $requestedMonth = $request->string('month')->value();
        $monthNumber = (int) substr($requestedMonth, 5, 2);
        $month = preg_match('/^\d{4}-\d{2}$/', $requestedMonth) && $monthNumber >= 1 && $monthNumber <= 12
            ? $requestedMonth : null;
        $query = ActivityLog::with('user')->where('is_noise', false);
        $tab === 'logins'
            ? $query->whereIn('action', ['login', 'logout', 'login_password'])
            : $query->whereNotIn('action', ['login', 'logout', 'login_password']);

        foreach (preg_split('/\s+/u', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
            $query->where(fn ($q) => $q->whereRaw("search_text LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('user', fn ($users) => $users->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern])));
        }
        $monthQuery = clone $query;

        if ($month) {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $query->whereBetween('created_at', [$start, $start->copy()->endOfMonth()]);
        }

        $months = $monthQuery
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->unique()
            ->values();

        return view($request->header('X-Activity-Results') === '1' ? 'activity-log.results' : 'activity-log.index', [
            'logs' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(50)->withQueryString(),
            'months' => $months,
            'selectedMonth' => $month,
            'tab' => $tab,
            'search' => $search,
        ]);
    }
}
