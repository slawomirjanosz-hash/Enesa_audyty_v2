<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\ProjectWorkOrder;
use App\Services\AuditorAccessService;
use App\Services\OfferContentService;
use App\Services\ProjectWorkOrderDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectWorkOrderController extends Controller
{
    private function access(Project $project, ?ProjectWorkOrder $order = null, bool $manage = false): void
    {
        $this->authorize('view', $project);
        abort_if($order && (int) $order->project_id !== (int) $project->id, 404);
        $user = request()->user();
        abort_if($manage && $user->hasFullReadOnlyAccess(), 403);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($user)
            || $user->can('projects.work_orders.manage')
            || (! $manage && $user->can('projects.work_orders.view')), 403);
    }

    public function create(Request $request, Project $project)
    {
        $this->access($project, null, true);
        $data = $request->validate([
            'source_id' => ['nullable', 'integer'],
            'requirement_ids' => ['nullable', 'array', 'max:200'],
            'requirement_ids.*' => ['integer', 'distinct'],
        ]);
        $order = new ProjectWorkOrder([
            'title' => 'Zlecenie realizacji prac', 'text_sections' => ProjectWorkOrder::defaultSections(),
            'price_sections' => [], 'stage_ids' => [], 'status' => 'draft', 'issued_on' => today(),
            'vat_rate' => 23, 'advance_cents' => 0, 'our_contact' => $request->user()->name, 'document_ids' => [],
        ]);
        if (! empty($data['source_id'])) {
            $source = ProjectWorkOrder::where('project_id', $project->id)->findOrFail($data['source_id']);
            $order->fill($source->only(['title', 'supplier_company_id', 'place', 'offer_reference', 'offer_date',
                'our_contact', 'supplier_contact', 'text_sections', 'price_sections', 'stage_ids', 'vat_rate', 'advance_cents', 'document_ids']));
        }
        if (! empty($data['requirement_ids'])) {
            $this->authorizeRequirementPrices($request);
            $items = $project->requirements()->whereIn('id', $data['requirement_ids'])->get();
            abort_unless($items->count() === count($data['requirement_ids']), 404);
            $suppliers = $items->pluck('supplier_company_id')->filter()->unique();
            if ($suppliers->count() > 1) {
                throw ValidationException::withMessages(['requirement_ids' => 'Jedno zlecenie może dotyczyć jednego wykonawcy. Wybierz pozycje tego samego dostawcy.']);
            }
            $order->supplier_company_id = $suppliers->first();
            $order->price_sections = [['name' => 'Materiały i usługi', 'rows' => $items->map(fn ($item) => [
                'opis' => $item->name, 'jedn' => $item->unit ?: 'szt.', 'ilosc' => (float) $item->quantity,
                'cena_jedn' => $item->quantity > 0 ? round((float) $item->estimated_cost / (float) $item->quantity, 2) : 0,
            ])->all()]];
        }

        return $this->form($project, $order);
    }

    private function authorizeRequirementPrices(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->hasRole('superadmin') || ($user->canAny(['projects.requirements.view', 'projects.requirements.manage'])
            && $user->can('projects.requirements.material_prices.view')
            && $user->can('projects.requirements.service_prices.view')), 403);
    }

    public function edit(Project $project, ProjectWorkOrder $workOrder)
    {
        $this->access($project, $workOrder, true);
        abort_unless($workOrder->status === 'draft', 409, 'Wystawione zlecenie ma zamrożoną treść. Utwórz kopię.');

        return $this->form($project, $workOrder);
    }

    private function form(Project $project, ProjectWorkOrder $order)
    {
        $supplier = $order->supplier_company_id ? Company::find($order->supplier_company_id) : null;

        return view('projects.work-orders.form', [
            'project' => $project, 'order' => $order, 'companySettings' => CompanySettings::first(),
            'suppliers' => Company::suppliers()->where(fn ($q) => $q->whereNull('archived_at')->orWhere('id', $order->supplier_company_id))->orderBy('name')->get(),
            'supplier' => $supplier, 'stages' => $project->tasks,
            'documents' => app(AuditorAccessService::class)->hasFullAccess(request()->user()) || request()->user()->can('projects.documents.view')
                ? $project->documents()->orderBy('original_filename')->get(['id', 'original_filename']) : collect(),
            'templates' => ProjectWorkOrder::where('project_id', $project->id)->where('is_template', true)->where('status', 'draft')->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function show(Project $project, ProjectWorkOrder $workOrder)
    {
        $this->access($project, $workOrder);

        return view('projects.work-orders.show', ['project' => $project, 'order' => $workOrder,
            'canManage' => ! request()->user()->hasFullReadOnlyAccess() && (app(AuditorAccessService::class)->hasFullAccess(request()->user()) || request()->user()->can('projects.work_orders.manage'))]);
    }

    public function store(Request $request, Project $project)
    {
        return $this->save($request, $project, new ProjectWorkOrder);
    }

    public function update(Request $request, Project $project, ProjectWorkOrder $workOrder)
    {
        return $this->save($request, $project, $workOrder);
    }

    private function save(Request $request, Project $project, ProjectWorkOrder $order)
    {
        $this->access($project, $order->exists ? $order : null, true);
        foreach (['text_sections', 'price_sections'] as $field) {
            if (is_string($request->input($field))) {
                $decoded = json_decode($request->input($field), true);
                if (! is_array($decoded)) {
                    throw ValidationException::withMessages([$field => 'Nieprawidłowe dane sekcji.']);
                }
                $request->merge([$field => $decoded]);
            }
        }
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'], 'title' => ['required', 'string', 'max:255'],
            'supplier_company_id' => ['nullable', Rule::exists('companies', 'id')->where('company_type', 'supplier')],
            'is_template' => ['nullable', 'boolean'], 'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'place' => ['nullable', 'string', 'max:255'], 'offer_reference' => ['nullable', 'string', 'max:255'],
            'offer_date' => ['nullable', 'date_format:Y-m-d'], 'our_contact' => ['nullable', 'string', 'max:255'],
            'supplier_contact' => ['nullable', 'string', 'max:1000'], 'notes' => ['nullable', 'string', 'max:10000'],
            'stage_ids' => ['sometimes', 'array', 'max:200'], 'stage_ids.*' => ['integer', 'distinct'],
            'document_ids' => ['sometimes', 'array', 'max:50'], 'document_ids.*' => ['integer', 'distinct'],
            'vat_rate' => ['required', Rule::in([0, 5, 8, 23])], 'advance' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,999999999.99'],
            'text_sections' => ['present', 'array', 'max:30'], 'text_sections.*.name' => ['required', 'string', 'max:120'],
            'text_sections.*.content' => ['nullable', 'string', 'max:40000'],
            'text_sections.*.placement' => ['required', Rule::in(['before_price', 'after_price'])],
            'price_sections' => ['present', 'array', 'max:20'], 'price_sections.*.name' => ['required', 'string', 'max:120'],
            'price_sections.*.rows' => ['required', 'array', 'min:1', 'max:200'],
            'price_sections.*.rows.*.opis' => ['required', 'string', 'max:1000'],
            'price_sections.*.rows.*.jedn' => ['required', 'string', 'max:30'],
            'price_sections.*.rows.*.ilosc' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000'],
            'price_sections.*.rows.*.cena_jedn' => ['required', 'numeric', 'decimal:0,2', 'between:0,9999999.99'],
        ]);
        $stageIds = array_map('intval', $data['stage_ids'] ?? []);
        $documentIds = array_map('intval', $data['document_ids'] ?? []);
        if ($documentIds) {
            abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()) || $request->user()->can('projects.documents.view'), 403);
        }
        $attachments = $project->documents()->whereIn('id', $documentIds)->get(['id', 'original_filename']);
        if ($attachments->count() !== count($documentIds)) {
            throw ValidationException::withMessages(['document_ids' => 'Wybierz dokumenty tego projektu.']);
        }
        if ($project->tasks()->whereIn('id', $stageIds)->count() !== count($stageIds)) {
            throw ValidationException::withMessages(['stage_ids' => 'Wybierz etapy tego projektu.']);
        }
        $content = app(OfferContentService::class);
        $data['text_sections'] = array_map(fn ($section) => [
            'name' => trim(strip_tags($section['name'])), 'content' => $content->cleanHtml($section['content'] ?? ''),
            'placement' => $section['placement'],
        ], $data['text_sections']);
        $net = 0;
        $data['price_sections'] = array_map(function ($section) use (&$net) {
            return ['name' => trim(strip_tags($section['name'])), 'rows' => array_map(function ($row) use (&$net) {
                $cents = (int) round(round((float) $row['ilosc'] * 100) * round((float) $row['cena_jedn'] * 100) / 100);
                $net += $cents;

                return ['opis' => $row['opis'], 'jedn' => $row['jedn'], 'ilosc' => $row['ilosc'],
                    'cena_jedn' => $row['cena_jedn'], 'net_cents' => $cents, 'z_narzutem' => $cents / 100];
            }, $section['rows'])];
        }, $data['price_sections']);
        if ($net > 99999999999) {
            throw ValidationException::withMessages(['price_sections' => 'Przekroczono maksymalną wartość zlecenia.']);
        }
        $advance = (int) round((float) ($data['advance'] ?? 0) * 100);
        $vat = (int) round($net * (int) $data['vat_rate'] / 100);
        if ($advance > $net + $vat) {
            throw ValidationException::withMessages(['advance' => 'Zaliczka nie może przekraczać wartości brutto zlecenia.']);
        }
        $revision = (int) $data['revision'];
        unset($data['revision'], $data['advance']);
        $data['stage_ids'] = $stageIds;
        $data['document_ids'] = $documentIds;
        $data['attachment_snapshot'] = $attachments->map(fn ($document) => ['id' => $document->id, 'name' => $document->original_filename])->all();
        $data['is_template'] = $request->boolean('is_template');
        DB::transaction(function () use ($project, $order, $data, $revision, $request, $net, $vat, $advance) {
            $issuer = CompanySettings::query()->lockForUpdate()->first();
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            if ($order->exists) {
                ProjectWorkOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $order->refresh();
                abort_unless($order->status === 'draft' && $order->revision === $revision, 409, 'Zlecenie zmieniono lub już wystawiono. Odśwież formularz.');
            }
            $number = $order->number;
            if (! $order->exists) {
                $prefix = 'ZLE/'.$project->protocolProjectReference().'/'.now()->format('Y').'/';
                $sequence = (int) (ProjectWorkOrder::withTrashed()->where('number', 'like', $prefix.'%')->pluck('number')
                    ->map(fn ($value) => (int) substr($value, strlen($prefix)))->max() ?? 0) + 1;
                $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            }
            $supplier = empty($data['supplier_company_id']) ? null : Company::findOrFail($data['supplier_company_id']);
            $order->fill($data);
            $order->forceFill([
                'project_id' => $project->id, 'number' => $number, 'status' => 'draft',
                'net_cents' => $net, 'vat_cents' => $vat, 'advance_cents' => $advance,
                'issuer_snapshot' => $this->issuerSnapshot($issuer),
                'supplier_snapshot' => $supplier?->only(['name', 'address', 'city', 'postcode', 'nip', 'email', 'phone']),
                'project_snapshot' => $project->only(['number', 'name']),
                'revision' => $order->exists ? $order->revision + 1 : 1, 'created_by' => $order->created_by ?? $request->user()->id,
                'history' => array_merge($order->history ?? [], [['status' => 'draft', 'action' => 'save', 'user' => $request->user()->name, 'user_id' => $request->user()->id, 'at' => now()->toIso8601String()]]),
            ])->save();
        });

        return redirect()->route('projects.work-orders.show', [$project, $order])->with('success', 'Zlecenie zapisano jako robocze.');
    }

    private function issuerSnapshot(?CompanySettings $issuer): array
    {
        return ($issuer?->only(['name', 'address', 'city', 'postcode', 'nip', 'email', 'phone', 'primary_color', 'offer_design_style', 'offer_tagline']) ?? ['name' => config('app.name')])
            + ['logo' => $issuer?->logoDataUri()];
    }

    public function transition(Request $request, Project $project, ProjectWorkOrder $workOrder)
    {
        $this->access($project, $workOrder, true);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ProjectWorkOrder::STATUSES))],
            'revision' => ['required', 'integer'],
            'confirmation_reference' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($workOrder, $data) {
            $order = ProjectWorkOrder::whereKey($workOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->revision === (int) $data['revision'], 409, 'Zlecenie zmieniono. Odśwież stronę.');
            if ($order->is_template || ! in_array($data['status'], ProjectWorkOrder::TRANSITIONS[$order->status], true)) {
                throw ValidationException::withMessages(['status' => 'Niedozwolona zmiana statusu.']);
            }
            if ($data['status'] === 'issued') {
                if (! $order->supplier_company_id || ! $order->issued_on || ! $order->our_contact || ! $order->supplier_contact
                    || ! $order->price_sections || ! collect($order->text_sections)->contains(fn ($s) => trim(strip_tags($s['content'])) !== '')) {
                    throw ValidationException::withMessages(['status' => 'Przed wystawieniem uzupełnij wykonawcę, datę, kontakty obu stron i zakres zlecenia.']);
                }
                $order->issued_at = now();
            }
            if ($data['status'] === 'confirmed') {
                if (blank($data['confirmation_reference'] ?? null)) {
                    throw ValidationException::withMessages(['confirmation_reference' => 'Wpisz źródło potwierdzenia wykonawcy, np. e-mail z datą.']);
                }
                $order->confirmation_reference = $data['confirmation_reference'];
                $order->confirmed_at = now();
            }
            $order->status = $data['status'];
            $order->history = array_merge($order->history ?? [], [['status' => $data['status'], 'action' => 'status', 'user' => request()->user()->name, 'user_id' => request()->user()->id, 'at' => now()->toIso8601String()]]);
            $order->revision++;
            $order->save();
        });

        return back()->with('success', 'Zmieniono status zlecenia.');
    }

    public function destroy(Project $project, ProjectWorkOrder $workOrder)
    {
        $this->access($project, $workOrder, true);
        DB::transaction(function () use ($workOrder) {
            $order = ProjectWorkOrder::whereKey($workOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'draft', 409, 'Wystawione zlecenie można anulować, ale nie usuwać.');
            $order->delete();
        });

        return redirect()->route('projects.show', [$project, 'tab' => 'work-orders'])->with('success', 'Usunięto robocze zlecenie.');
    }

    public function document(Request $request, Project $project, ProjectWorkOrder $workOrder, ProjectWorkOrderDocument $documents)
    {
        $this->access($project, $workOrder);
        $format = $request->validate(['format' => ['nullable', Rule::in(['pdf', 'docx'])]])['format'] ?? 'pdf';

        return $documents->response($workOrder, $format, $request->boolean('download'));
    }
}
