<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\HrLeave;
use App\Support\HrAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class HrLeaveController extends Controller
{
    public function storeLeave(Request $request): RedirectResponse
    {
        $data = $this->leaveData($request);
        $data['created_by'] = $request->user()->id;
        HrLeave::create($data);

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'Nieobecność została dodana.');
    }

    public function updateLeave(Request $request, HrLeave $leave): RedirectResponse
    {
        abort_unless($leave->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);
        $leave->update($this->leaveData($request, $leave->user_id));

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'Nieobecność została zaktualizowana.');
    }

    public function leavePdf(Request $request, HrLeave $leave): Response
    {
        abort_unless($leave->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);
        $leave->load('user');
        $company = CompanySettings::query()->first();

        return Pdf::loadView('hr.leave-pdf', [
            'leave' => $leave,
            'company' => $company,
            'logo' => $company?->logoDataUri(),
        ])->setPaper('a5', 'portrait')->download(
            'urlop-'.Str::slug($leave->user?->name ?: 'pracownik').'-'.$leave->start_date->format('Y-m-d').'.pdf'
        );
    }

    public function destroyLeave(Request $request, HrLeave $leave): RedirectResponse
    {
        abort_unless($leave->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);
        $leave->delete();

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'Nieobecność została usunięta.');
    }

    private function leaveData(Request $request, ?int $forcedUserId = null): array
    {
        $data = $request->validateWithBag('leave', [
            'user_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'string', Rule::in(array_keys(HrLeave::TYPES))],
            'start_date' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:730'],
            'include_weekends' => ['nullable', 'boolean'],
            'document_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $user = $request->user();
        $data['user_id'] = $forcedUserId ?? (HrAccess::canViewTeam($user) && ! empty($data['user_id']) ? (int) $data['user_id'] : $user->id);
        $data['include_weekends'] = $request->boolean('include_weekends');
        $endDate = Carbon::parse($data['start_date']);
        if ($data['include_weekends']) {
            $endDate->addDays((int) $data['days'] - 1);
        } else {
            $daysLeft = (int) $data['days'];
            while (true) {
                if ($endDate->isWeekday()) {
                    $daysLeft--;
                }
                if ($daysLeft === 0) {
                    break;
                }
                $endDate->addDay();
            }
        }
        $data['end_date'] = $endDate->toDateString();

        return $data;
    }
}
