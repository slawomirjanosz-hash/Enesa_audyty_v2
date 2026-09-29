<?php

namespace App\Http\Controllers;

use App\Models\HrAttendance;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HrAttendanceController extends Controller
{
    public function storeAttendance(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('attendance', ['user_id' => ['nullable', 'exists:users,id'], 'work_date' => ['required', 'date'], 'started_at' => ['nullable', 'date_format:H:i'], 'finished_at' => ['nullable', 'date_format:H:i', 'after:started_at'], 'status' => ['required', 'in:present,remote,leave,sick,absent'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $userId = HrAccess::canViewTeam($request->user()) && ! empty($data['user_id']) ? (int) $data['user_id'] : $request->user()->id;
        $data['user_id'] = $userId;
        HrAttendance::updateOrCreate(['user_id' => $userId, 'work_date' => $data['work_date']], $data);

        return redirect()->route('hr.index', ['tab' => 'attendance'])->with('success', 'Wpis na liście obecności został zapisany.');
    }

    public function destroyAttendance(Request $request, HrAttendance $attendance): RedirectResponse
    {
        abort_unless($attendance->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);
        $attendance->delete();

        return redirect()->route('hr.index', ['tab' => 'attendance'])->with('success', 'Wpis obecności został usunięty.');
    }
}
