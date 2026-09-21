<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Services\AuditorAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectProtocolController extends Controller
{
    private function access(Project $project, ?ProjectProtocol $protocol = null, bool $manage = false): void
    {
        $this->authorize('view', $project);
        abort_if($protocol && $protocol->project_id !== $project->id, 404);
        $user = request()->user();
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($user)
            || $user->can('projects.protocols.manage') || (! $manage && $user->can('projects.protocols.view')), 403);
    }

    public function create(Project $project)
    {
        $this->access($project, null, true);

        return $this->form($project, new ProjectProtocol);
    }

    public function edit(Project $project, ProjectProtocol $protocol)
    {
        $this->access($project, $protocol, true);

        return $this->form($project, $protocol);
    }

    public function copy(Project $project, ProjectProtocol $protocol)
    {
        $this->access($project, $protocol, true);
        $copy = new ProjectProtocol($protocol->only([
            'supplier_company_id', 'place', 'reference', 'kind', 'outcome', 'description',
            'remarks', 'invoice_conditions', 'attachments', 'supplier_representative', 'items',
        ]));
        $copy->acceptance_date = today();
        $copy->receiver_name = request()->user()->name;
        $copy->invoice_decision = 'no';

        return $this->form($project, $copy)->with('copiedFrom', $protocol->number);
    }

    private function form(Project $project, ProjectProtocol $protocol)
    {
        return view('projects.protocols.form', [
            'project' => $project, 'protocol' => $protocol,
            'suppliers' => Company::suppliers()->where(fn ($q) => $q->whereNull('archived_at')->orWhere('id', $protocol->supplier_company_id))->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        return $this->save($request, $project, new ProjectProtocol);
    }

    public function update(Request $request, Project $project, ProjectProtocol $protocol)
    {
        return $this->save($request, $project, $protocol);
    }

    private function save(Request $request, Project $project, ProjectProtocol $protocol)
    {
        $this->access($project, $protocol->exists ? $protocol : null, true);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'supplier_company_id' => ['required', Rule::exists('companies', 'id')->where('company_type', 'supplier')],
            'acceptance_date' => ['required', 'date_format:Y-m-d'],
            'place' => ['nullable', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', Rule::in(array_keys(ProjectProtocol::KINDS))],
            'outcome' => ['required', Rule::in(array_keys(ProjectProtocol::OUTCOMES))],
            'invoice_decision' => ['required', Rule::in(array_keys(ProjectProtocol::INVOICES))],
            'description' => ['required', 'string', 'max:15000'],
            'remarks' => ['required_unless:outcome,accepted', 'nullable', 'string', 'max:15000'],
            'remedy_deadline' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:acceptance_date'],
            'invoice_conditions' => ['required_if:invoice_decision,conditional', 'nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'string', 'max:5000'],
            'receiver_name' => ['required', 'string', 'max:255'], 'supplier_representative' => ['required', 'string', 'max:255'],
            'use_signature' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:10000000'],
            'items.*.vat' => ['required', Rule::in(['0', '5', '8', '23', 'zw', 'np'])],
        ]);
        if ($data['outcome'] === 'rejected' && $data['invoice_decision'] !== 'no') {
            throw ValidationException::withMessages(['invoice_decision' => 'Przy odmowie odbioru nie można zezwolić na fakturowanie.']);
        }
        if ($data['outcome'] === 'accepted' && ! empty($data['remarks'])) {
            throw ValidationException::withMessages(['outcome' => 'Wpisano uwagi. Wybierz odbiór z uwagami.']);
        }
        $signature = $request->boolean('use_signature') ? $request->user()->signatureDataUri() : null;
        if ($request->boolean('use_signature') && (! $signature || $data['receiver_name'] !== $request->user()->name)) {
            throw ValidationException::withMessages(['use_signature' => 'Możesz dołączyć wyłącznie własny zapisany podpis, ze swoim imieniem i nazwiskiem odbierającego.']);
        }
        $revision = $data['revision'];
        unset($data['revision'], $data['use_signature']);
        $data['items'] = array_map(function ($row) {
            $net = (int) round(round((float) $row['quantity'] * 100) * round((float) $row['price'] * 100) / 100);

            return array_intersect_key($row, array_flip(['name', 'quantity', 'unit', 'price', 'vat']))
                + ['net_cents' => $net, 'vat_cents' => (int) round($net * (is_numeric($row['vat']) ? (int) $row['vat'] : 0) / 100)];
        }, $data['items']);
        DB::transaction(function () use ($project, $protocol, $data, $signature, $revision, $request) {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $existing = $protocol->exists;
            if ($existing) {
                $protocol->refresh();
                abort_if($protocol->revision !== (int) $revision, 409, 'Protokół zmieniono w innym oknie. Odśwież formularz.');
            }
            $supplier = Company::findOrFail($data['supplier_company_id']);
            $issuer = CompanySettings::first();
            $snapshot = $existing ? $protocol->issuer_snapshot : [
                'name' => $issuer?->name ?: config('app.name'), 'address' => $issuer?->address, 'city' => $issuer?->city, 'postcode' => $issuer?->postcode, 'nip' => $issuer?->nip,
                'logo' => $issuer && ($issuer->logo_data || ($issuer->logo_path && Storage::disk('public')->exists($issuer->logo_path))) ? $issuer->logoDataUri() : null,
                'color' => $issuer?->primaryColor() ?? '#1A4D3A',
                'project_number' => $project->number, 'project_name' => $project->name,
            ];
            $supplierSnapshot = $existing && $protocol->supplier_company_id === $supplier->id
                ? $protocol->supplier_snapshot : $supplier->only(['name', 'address', 'city', 'nip']);
            $protocol->fill($data + ['project_id' => $project->id, 'issuer_snapshot' => $snapshot, 'supplier_snapshot' => $supplierSnapshot,
                'signature_data' => $signature, 'revision' => $existing ? $protocol->revision + 1 : 1]);
            if (! $existing) {
                $protocol->created_by = $request->user()->id;
                $sequence = ProjectProtocol::where('project_id', $project->id)->count() + 1;
                $protocol->number = 'PO/'.$project->id.'/'.now()->format('Y').'/'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            }
            $protocol->save();
        });

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'protocols'])->with('success', 'Protokół został zapisany.');
    }

    public function pdf(Request $request, Project $project, ProjectProtocol $protocol)
    {
        $this->access($project, $protocol);
        ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'download', 'auditable_type' => ProjectProtocol::class,
            'auditable_id' => $protocol->id, 'subject_label' => $protocol->number]);
        $pdf = Pdf::loadView('projects.protocols.pdf', compact('protocol'))->setPaper('a4');
        $filename = $protocol->pdfFilename();

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
