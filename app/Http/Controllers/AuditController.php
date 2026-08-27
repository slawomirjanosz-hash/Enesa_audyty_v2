<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditFinancialEntry;
use App\Models\AuditSurvey;
use App\Models\AuditType;
use App\Models\Document;
use App\Models\EnergyPassport;
use App\Models\EnergyPassportTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditorAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __construct(private readonly AuditorAccessService $access) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('auditCreate', [
            'company_id' => ['required', 'exists:companies,id'], 'number' => ['required', 'string', 'max:80', 'unique:audits,number'],
            'title' => ['required', 'string', 'max:255'], 'manager_id' => ['required', 'exists:users,id'],
            'status' => ['required', 'in:draft,in_progress,done,cancelled'], 'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'contract_value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'], 'member_ids' => ['nullable', 'array'], 'member_ids.*' => ['integer', 'exists:users,id'],
        ]);
        abort_unless($this->access->canViewCompany($request->user(), (int) $data['company_id'], 'can_view_audits'), 403);
        $members = $data['member_ids'] ?? [];
        unset($data['member_ids']);
        $audit = Audit::create($data + ['created_by' => $request->user()->id]);
        $audit->members()->sync(array_unique([...$members, (int) $audit->manager_id]));

        return redirect()->route('audits.show', $audit)->with('success', 'Audyt został utworzony.');
    }

    public function show(Request $request, Audit $audit): View
    {
        $this->ensureAccess($request, $audit);
        $audit->load(['company', 'manager', 'members', 'tasks.assignedUser', 'financialEntries', 'documents.uploader', 'surveys.auditType', 'energyPassports.template']);

        return view('audits.show', [
            'audit' => $audit,
            'users' => User::query()->where('is_active', true)->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))->orderBy('name')->get(),
            'passportTemplates' => EnergyPassportTemplate::query()->orderBy('category')->orderBy('name')->get(),
            'auditTypes' => AuditType::query()->orderBy('name')->get(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, Audit $audit): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        $data = $request->validate([
            'number' => ['required', 'string', 'max:80', 'unique:audits,number,'.$audit->id], 'title' => ['required', 'string', 'max:255'],
            'manager_id' => ['required', 'exists:users,id'], 'status' => ['required', 'in:draft,in_progress,done,cancelled'],
            'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0'], 'description' => ['nullable', 'string'],
            'member_ids' => ['nullable', 'array'], 'member_ids.*' => ['integer', 'exists:users,id'],
        ]);
        $members = $data['member_ids'] ?? [];
        unset($data['member_ids']);
        $audit->update($data);
        $audit->members()->sync(array_unique([...$members, (int) $audit->manager_id]));

        return back()->with('success', 'Dane audytu zostały zapisane.');
    }

    public function storeTask(Request $request, Audit $audit): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'assigned_to' => ['nullable', 'exists:users,id'], 'start_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:start_date'], 'status' => ['required', 'in:todo,in_progress,done'], 'priority' => ['required', 'in:low,medium,high'], 'progress' => ['required', 'integer', 'between:0,100']]);
        $audit->tasks()->create($data + ['company_id' => $audit->company_id, 'created_by' => $request->user()->id, 'project_position' => ((int) $audit->tasks()->max('project_position')) + 1]);

        return back()->with('success', 'Zadanie audytowe zostało dodane.');
    }

    public function destroyTask(Request $request, Audit $audit, Task $task): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        abort_unless($task->audit_id === $audit->id, 404);
        $task->delete();

        return back()->with('success', 'Zadanie zostało usunięte.');
    }

    public function updateTask(Request $request, Audit $audit, Task $task): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        abort_unless($task->audit_id === $audit->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'assigned_to' => ['nullable', 'exists:users,id'], 'start_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:start_date'], 'status' => ['required', 'in:todo,in_progress,done'], 'priority' => ['required', 'in:low,medium,high'], 'progress' => ['required', 'integer', 'between:0,100']]);
        $task->update($data);

        return redirect()->route('audits.show', ['audit' => $audit, 'tab' => 'schedule'])->with('success', 'Zadanie zostało zaktualizowane.');
    }

    public function storeFinance(Request $request, Audit $audit): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        $data = $request->validate(['type' => ['required', 'in:cost,invoice'], 'name' => ['required', 'string', 'max:255'], 'document_number' => ['nullable', 'string', 'max:100'], 'entry_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:0'], 'status' => ['required', 'in:planned,issued,paid'], 'notes' => ['nullable', 'string']]);
        $audit->financialEntries()->create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Pozycja finansowa została dodana.');
    }

    public function destroyFinance(Request $request, Audit $audit, AuditFinancialEntry $entry): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        abort_unless($entry->audit_id === $audit->id, 404);
        $entry->delete();

        return back()->with('success', 'Pozycja finansowa została usunięta.');
    }

    public function updateFinance(Request $request, Audit $audit, AuditFinancialEntry $entry): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        abort_unless($entry->audit_id === $audit->id, 404);
        $data = $request->validate(['type' => ['required', 'in:cost,invoice'], 'name' => ['required', 'string', 'max:255'], 'document_number' => ['nullable', 'string', 'max:100'], 'entry_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:0'], 'status' => ['required', 'in:planned,issued,paid'], 'notes' => ['nullable', 'string']]);
        $entry->update($data);

        return redirect()->route('audits.show', ['audit' => $audit, 'tab' => 'finances'])->with('success', 'Pozycja finansowa została zaktualizowana.');
    }

    public function storeSurvey(Request $request, Audit $audit): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        $data = $request->validate(['audit_type_id' => ['required', 'exists:audit_types,id'], 'status' => ['required', 'in:draft,ready,completed'], 'notes' => ['nullable', 'string']]);
        $auditType = AuditType::query()->findOrFail($data['audit_type_id']);
        $currentVersion = $auditType->currentVersion();
        $audit->surveys()->create($data + [
            'title' => $auditType->name,
            'audit_type_version_id' => $currentVersion?->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Ankieta audytowa została dodana.');
    }

    public function destroySurvey(Request $request, Audit $audit, AuditSurvey $survey): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        abort_unless($survey->audit_id === $audit->id, 404);
        $survey->delete();

        return back()->with('success', 'Ankieta została usunięta.');
    }

    public function storePassport(Request $request, Audit $audit): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        $data = $request->validate(['template_id' => ['required', 'exists:energy_passport_templates,id'], 'name' => ['required', 'string', 'max:255'], 'asset_identifier' => ['nullable', 'string', 'max:120'], 'location' => ['nullable', 'string', 'max:255']]);
        $passport = EnergyPassport::create($data + ['audit_id' => $audit->id, 'company_id' => $audit->company_id, 'status' => 'draft', 'created_by' => $request->user()->id]);

        return redirect()->route('energy-passports.edit', $passport)->with('success', 'Paszport został dodany do audytu.');
    }

    public function storeDocument(Request $request, Audit $audit): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip']]);
        $file = $data['file'];
        $name = now()->format('YmdHis').'_'.Str::random(10).'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = 'audits/'.$audit->id.'/'.$name;
        Storage::disk('local')->put($path, $file->getContent());
        Document::create(['audit_id' => $audit->id, 'company_id' => $audit->company_id, 'type' => 'upload', 'original_filename' => $file->getClientOriginalName(), 'stored_path' => $path, 'mime_type' => $file->getClientMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id]);

        return back()->with('success', 'Dokument audytu został dodany.');
    }

    public function downloadDocument(Request $request, Audit $audit, Document $document)
    {
        $this->ensureAccess($request, $audit);
        abort_unless($document->audit_id === $audit->id, 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function destroyDocument(Request $request, Audit $audit, Document $document): RedirectResponse
    {
        $this->ensureAccess($request, $audit);
        abort_unless($document->audit_id === $audit->id, 404);
        Storage::disk('local')->delete($document->stored_path);
        $document->delete();

        return back()->with('success', 'Dokument został usunięty.');
    }

    private function ensureAccess(Request $request, Audit $audit): void
    {
        abort_unless($this->access->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);
    }

    private function canManage(Request $request): bool
    {
        return $this->access->hasFullAccess($request->user()) || $request->user()->can('audits.manage');
    }
}
