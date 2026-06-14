<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Company;
use App\Models\Offer;
use App\Models\Task;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $companies = Company::active()->with(['audits', 'offers', 'users'])->get();

        $stats = [
            'active_audits'      => Audit::where('status', 'in_progress')->count(),
            'pending_offers'     => Offer::whereIn('status', ['draft', 'sent'])->count(),
            'new_registrations'  => Company::where('status', 'pending')->count(),
            'overdue_tasks'      => Task::where('due_date', '<', now())
                                        ->where('status', '!=', 'done')
                                        ->count(),
        ];

        return view('dashboard', compact('companies', 'stats'));
    }
}
