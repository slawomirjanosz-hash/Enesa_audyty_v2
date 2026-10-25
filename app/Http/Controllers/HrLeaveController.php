<?php

namespace App\Http\Controllers;

use App\Mail\HrLeaveApproved;
use App\Models\CompanySettings;
use App\Models\HrLeave;
use App\Models\HrLeaveApprovalTask;
use App\Models\User;
use App\Support\HrAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class HrLeaveController extends Controller
{
    public function storeLeave(Request $request): RedirectResponse
    {
        $data = $this->leaveData($request);
        $data['created_by'] = $request->user()->id;
        DB::transaction(function () use ($data): void {
            $data += $this->approvalData($data);
            $leave = HrLeave::create($data);
            $this->syncApprovalTask($leave);
        });

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'Wniosek został wysłany do przełożonego (L4 jest rejestrowane bez akceptacji).');
    }

    public function updateLeave(Request $request, HrLeave $leave): RedirectResponse
    {
        DB::transaction(function () use ($request, $leave): void {
            $current = HrLeave::lockForUpdate()->findOrFail($leave->id);
            $this->ensureEditable($request, $current);
            $data = $this->leaveData($request, $current->user_id);
            if (in_array($current->approval_status, ['pending', 'rejected'], true)) {
                $request->validateWithBag('leave', ['approval_revision' => ['required', 'integer']]);
                abort_unless($current->approval_revision === (int) $request->input('approval_revision'), 409, 'Wniosek zmienił się. Odśwież stronę.');
            }
            $data += $this->approvalData($data);
            $current->fill($data)->forceFill(['approval_revision' => $current->approval_revision + 1])->save();
            $this->syncApprovalTask($current);
        });

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'Nieobecność została zaktualizowana.');
    }

    public function leavePdf(Request $request, HrLeave $leave): Response
    {
        abort_unless(HrAccess::canViewLeave($request->user(), $leave), 403);
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
        DB::transaction(function () use ($request, $leave): void {
            $current = HrLeave::lockForUpdate()->findOrFail($leave->id);
            $this->ensureEditable($request, $current);
            if (! in_array($current->approval_status, ['legacy', 'recorded'], true)) {
                $request->validate(['approval_revision' => ['required', 'integer']]);
                abort_unless($current->approval_revision === (int) $request->input('approval_revision'), 409);
            }
            $current->delete();
        });

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'Nieobecność została usunięta.');
    }

    public function decide(Request $request, HrLeave $leave): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'approval_revision' => ['required', 'integer'], 'decision_note' => ['nullable', 'string', 'max:2000']]);
        $approved = DB::transaction(function () use ($request, $leave, $data) {
            $current = HrLeave::lockForUpdate()->findOrFail($leave->id);
            abort_unless($current->approver_id === $request->user()->id && $current->user_id !== $request->user()->id, 403);
            abort_unless($current->approval_status === 'pending' && $current->approval_revision === (int) $data['approval_revision'], 409, 'Wniosek został już rozpatrzony lub zmieniony. Odśwież stronę.');
            $current->forceFill(['approval_status' => $data['decision'], 'decision_note' => $data['decision_note'] ?? null, 'decided_at' => now(), 'approval_revision' => $current->approval_revision + 1])->save();
            HrLeaveApprovalTask::where('hr_leave_id', $current->id)->update(['status' => 'done', 'archived_at' => now(), 'updated_at' => now()]);

            return $current;
        });
        $message = 'Decyzja została zapisana. Zadanie jest wykonane i zarchiwizowane.';
        if ($approved->approval_status === 'approved') {
            try {
                Mail::to($approved->user->email)->send(new HrLeaveApproved($approved));
            } catch (\Throwable $error) {
                Log::warning('hr.leave.approval_mail_failed', ['leave_id' => $approved->id, 'error_type' => get_class($error)]);
                $message .= ' Nie udało się wysłać e-maila do pracownika. Status akceptacji jest widoczny w jego urlopach.';
            }
        }

        return redirect()->route('hr.index', ['tab' => 'leaves', 'leave_scope' => 'team'])->with('success', $message);
    }

    private function ensureEditable(Request $request, HrLeave $leave): void
    {
        $legacy = in_array($leave->approval_status, ['legacy', 'recorded'], true);
        abort_unless(($legacy && ($leave->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()))) || ($leave->user_id === $request->user()->id && in_array($leave->approval_status, ['pending', 'rejected'], true)), 403, 'Rozpatrzonego wniosku nie można edytować ani usunąć.');
    }

    private function approvalData(array $data): array
    {
        if ($data['type'] === 'sick_leave') {
            return ['approval_status' => 'recorded', 'approver_id' => null, 'approver_name' => null, 'decided_at' => null, 'decision_note' => null];
        }
        $employee = User::findOrFail($data['user_id']);
        $manager = $employee->supervisor;
        if (! $manager || ! $manager->is_active || $manager->id === $employee->id || $manager->hasAnyRole(['client_admin', 'client_user']) || ! ($manager->hasRole('superadmin') || $manager->canAny(['hr.leaves.view', 'system.full_access']))) {
            throw ValidationException::withMessages(['supervisor' => 'Nie wskazano aktywnego przełożonego z dostępem do urlopów. Poproś osobę uprawnioną o ustawienie przełożonego w profilu pracownika.'])->errorBag('leave');
        }

        return ['approval_status' => 'pending', 'approver_id' => $manager->id, 'approver_name' => $manager->name, 'decided_at' => null, 'decision_note' => null];
    }

    private function syncApprovalTask(HrLeave $leave): void
    {
        if ($leave->approval_status === 'pending') {
            HrLeaveApprovalTask::updateOrCreate(['hr_leave_id' => $leave->id], ['assigned_to' => $leave->approver_id, 'status' => 'in_progress', 'archived_at' => null]);
        } elseif ($leave->approval_status === 'recorded') {
            HrLeaveApprovalTask::where('hr_leave_id', $leave->id)->delete();
        }
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
        $employee = User::findOrFail($data['user_id']);
        abort_if(! $employee->is_active || $employee->roles->isEmpty() || $employee->hasAnyRole(['client_admin', 'client_user']), 422, 'Wybierz aktywnego pracownika.');
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
