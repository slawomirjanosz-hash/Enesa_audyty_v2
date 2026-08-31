<?php

namespace App\Http\Controllers\Client;

use App\Exports\ProjectGanttExport;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\Document;
use App\Models\IsoTrainingVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $company = request()->user()->companies()->first();
        if (! $company) {
            return redirect()->route('client.dashboard')->with('error', 'Brak przypisanej firmy.');
        }
        $audits = $company->audits()->with('manager')
            ->withCount(['tasks', 'documents', 'surveys', 'energyPassports'])
            ->orderByDesc('created_at')->get();

        return view('client.audits', compact('company', 'audits'));
    }

    public function show(Request $request, Audit $audit): View
    {
        $company = $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();
        $audit->load(['company', 'manager', 'members', 'tasks.assignedUser', 'documents.uploader', 'surveys.auditType', 'energyPassports.template']);
        $isIso50001 = $audit->surveys->contains(fn ($survey) => $survey->auditType?->slug === 'iso50001');
        $timelineItems = $audit->tasks->filter(fn ($task) => $task->start_date && $task->due_date)->map(fn ($task) => [
            'kind' => $task->is_milestone ? 'milestone' : 'task', 'id' => 'task-'.$task->id, 'db_id' => $task->id,
            'name' => $task->title, 'start' => $task->start_date->format('Y-m-d'), 'end' => $task->due_date->format('Y-m-d'),
            'progress' => $task->progress, 'status' => $task->status, 'priority' => $task->priority, 'description' => $task->description,
            'assigned_to' => $task->assigned_to, 'assignee' => $task->assignedUser?->name, 'is_milestone' => $task->is_milestone,
            'dependencies' => $task->depends_on_task_id ? 'task-'.$task->depends_on_task_id : '', 'position' => $task->project_position,
        ])->values();

        return view('audits.show', [
            'audit' => $audit, 'timelineItems' => $timelineItems, 'canManage' => false,
            'clientView' => true, 'canViewFinances' => false, 'users' => collect(),
            'auditTypes' => collect(), 'passportTemplates' => collect(), 'company' => $company,
            'clientAuditMode' => $isIso50001, 'clientAudit' => $audit,
            'isoChapters' => config('iso50001.chapters', []),
            'trainingVideos' => IsoTrainingVideo::query()->latest()->get(),
        ]);
    }

    public function downloadDocument(Request $request, Audit $audit, Document $document)
    {
        $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();
        abort_unless($document->audit_id === $audit->id, 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function exportGantt(Request $request, Audit $audit): BinaryFileResponse
    {
        $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();

        return Excel::download(new ProjectGanttExport($audit), 'Harmonogram_audytu_'.$audit->id.'.xlsx');
    }
}
