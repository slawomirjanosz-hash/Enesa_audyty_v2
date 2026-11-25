<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\ProjectWorkOrder;
use App\Models\User;
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

    public function create(Request $request, Project $project)
    {
        $this->access($project, null, true);

        $data = $request->validate(['direction' => ['sometimes', Rule::in(array_keys(ProjectProtocol::DIRECTIONS))], 'work_order_id' => ['nullable', 'integer']]);
        if (! empty($data['work_order_id'])) {
            $source = $this->sourceOrder($project, (int) $data['work_order_id']);
            $scope = collect($source->text_sections)->where('placement', 'before_price')
                ->map(fn ($section) => $section['name'].': '.trim(strip_tags(str_replace(['</p>', '</li>', '<br>'], "\n", $section['content']))))
                ->implode("\n\n");
            $protocol = new ProjectProtocol([
                'direction' => 'supplier', 'work_order_id' => $source->id,
                'supplier_company_id' => $source->supplier_company_id, 'reference' => $source->number,
                'title' => 'Odbiór: '.$source->title, 'description' => mb_substr($scope, 0, 15000),
                'items_mode' => 'manual', 'manual_items_description' => mb_substr(collect($source->price_sections)
                    ->flatMap(fn ($section) => collect($section['rows'])->pluck('opis'))->implode("\n"), 0, 15000) ?: 'Zakres zgodnie ze zleceniem '.$source->number,
            ]);

            return $this->form($project, $protocol);
        }

        return $this->form($project, new ProjectProtocol($data));
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
            'supplier_company_id', 'place', 'reference', 'kind', 'outcome', 'description', 'title',
            'remarks', 'invoice_conditions', 'attachments', 'supplier_representative', 'items',
            'items_mode', 'manual_items_description', 'direction', 'work_order_id',
        ]));
        $copy->acceptance_date = $protocol->acceptance_date ? today() : null;
        $copy->receiver_name = $copy->isClientAcceptance() ? $protocol->receiver_name : request()->user()->name;
        $copy->invoice_decision = 'no';

        return $this->form($project, $copy)->with('copiedFrom', $protocol->number);
    }

    private function form(Project $project, ProjectProtocol $protocol)
    {
        return view('projects.protocols.form', [
            'project' => $project, 'protocol' => $protocol,
            'representatives' => $this->representativeNames(),
            'suppliers' => $protocol->isClientAcceptance() ? collect() : Company::suppliers()->where(fn ($q) => $q->whereNull('archived_at')->orWhere('id', $protocol->supplier_company_id))->orderBy('name')->get(),
        ]);
    }

    private function representativeNames(): array
    {
        return User::where('is_active', true)
            ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', ['client_admin', 'client_user']))
            ->orderBy('name')->pluck('name')->unique()->values()->all();
    }

    private function sourceOrder(Project $project, int $id): ProjectWorkOrder
    {
        $user = request()->user();
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($user) || $user->canAny(['projects.work_orders.view', 'projects.work_orders.manage']), 403);
        $order = ProjectWorkOrder::where('project_id', $project->id)->findOrFail($id);
        abort_unless(! $order->is_template && in_array($order->status, ['issued', 'confirmed', 'in_progress', 'received'], true), 422, 'Protokół można utworzyć z wystawionego zlecenia.');

        return $order;
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
        $type = $request->validate(['direction' => ['sometimes', Rule::in(array_keys(ProjectProtocol::DIRECTIONS))]]);
        $direction = $protocol->exists ? $protocol->direction : ($type['direction'] ?? 'supplier');
        $client = $direction === 'client';
        $workOrderId = $request->validate(['work_order_id' => ['nullable', 'integer']])['work_order_id'] ?? null;
        if ($protocol->exists && (int) $workOrderId !== (int) $protocol->work_order_id) {
            throw ValidationException::withMessages(['work_order_id' => 'Powiązania ze zleceniem nie można zmienić po zapisaniu.']);
        }
        $sourceOrder = $workOrderId ? $this->sourceOrder($project, (int) $workOrderId) : null;
        if ($sourceOrder && ($client || (int) $request->input('supplier_company_id') !== (int) $sourceOrder->supplier_company_id)) {
            throw ValidationException::withMessages(['supplier_company_id' => 'Wykonawca musi być zgodny ze zleceniem.']);
        }
        $internal = $client ? 'supplier_representative' : 'receiver_name';
        $people = $request->validate([
            'receivers' => ['sometimes', 'array', 'max:20'],
            'receivers.*' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'representatives' => ['sometimes', 'array', 'max:20'],
            'representatives.*' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
        ]);
        foreach (['receivers' => 'receiver_name', 'representatives' => 'supplier_representative'] as $input => $field) {
            if (! array_key_exists($input, $people)) {
                continue;
            }
            $names = array_values(array_unique(array_filter($people[$input], fn ($name) => filled($name))));
            if ($field === $internal && array_diff($names, array_merge($this->representativeNames(), $protocol->peopleNames($field)))) {
                throw ValidationException::withMessages([$input => 'Wybierz przedstawicieli z listy.']);
            }
            $request->merge([$field => implode("\n", $names)]);
        }
        if ($client) {
            $request->merge(['items_mode' => 'manual', 'invoice_decision' => 'no', 'use_signature' => false]);
        }
        $manual = $request->input('items_mode', 'detailed') === 'manual';
        // Never retain hidden prices, even if a crafted request includes them.
        if ($manual) {
            $request->merge(['items' => []]);
        }
        $data = $request->validate([
            'items_mode' => ['sometimes', 'required', Rule::in(['detailed', 'manual'])],
            'manual_items_description' => [$client ? 'exclude' : 'exclude_unless:items_mode,manual', 'required', 'string', 'max:15000'],
            'revision' => ['required', 'integer', 'min:0'],
            'supplier_company_id' => $client ? ['exclude'] : ['required', Rule::exists('companies', 'id')->where('company_type', 'supplier')],
            'acceptance_date' => ['nullable', 'date_format:Y-m-d'],
            'place' => ['nullable', 'string', 'max:255'], 'reference' => [$client ? 'required' : 'nullable', 'string', 'max:255'],
            'kind' => ['required', Rule::in(array_keys(ProjectProtocol::KINDS))],
            'outcome' => ['required', Rule::in(array_keys(ProjectProtocol::OUTCOMES))],
            'invoice_decision' => ['required', Rule::in(array_keys(ProjectProtocol::INVOICES))],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:15000'],
            'remarks' => ['required_unless:outcome,accepted', 'nullable', 'string', 'max:15000'],
            'remedy_deadline' => ['nullable', 'date_format:Y-m-d'],
            'invoice_conditions' => $client ? ['exclude'] : ['required_if:invoice_decision,conditional', 'nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'string', 'max:5000'],
            'receiver_name' => ['nullable', 'string', 'max:255'], 'supplier_representative' => ['nullable', 'string', 'max:255'],
            'use_signature' => ['nullable', 'boolean'],
            'items' => [$manual ? 'present' : 'required', 'array', $manual ? 'max:0' : 'min:1', 'max:100'],
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
        $data['receiver_name'] ??= '';
        $data['supplier_representative'] ??= '';
        $signature = $request->boolean('use_signature') ? $request->user()->signatureDataUri() : null;
        if ($request->boolean('use_signature') && (! $signature || $data['receiver_name'] !== $request->user()->name)) {
            throw ValidationException::withMessages(['use_signature' => 'Możesz dołączyć wyłącznie własny zapisany podpis, ze swoim imieniem i nazwiskiem odbierającego.']);
        }
        $revision = $data['revision'];
        $data['items_mode'] = $manual ? 'manual' : 'detailed';
        $data['manual_items_description'] = $data['manual_items_description'] ?? null;
        $data['direction'] = $direction;
        $data['work_order_id'] = $sourceOrder?->id;
        unset($data['revision'], $data['use_signature']);
        $data['items'] = array_map(function ($row) {
            $net = (int) round(round((float) $row['quantity'] * 100) * round((float) $row['price'] * 100) / 100);

            return array_intersect_key($row, array_flip(['name', 'quantity', 'unit', 'price', 'vat']))
                + ['net_cents' => $net, 'vat_cents' => (int) round($net * (is_numeric($row['vat']) ? (int) $row['vat'] : 0) / 100)];
        }, $data['items']);
        DB::transaction(function () use ($project, $protocol, $data, $signature, $revision, $request, $client) {
            // Serialize numbering across projects that can share a manually entered suffix.
            $issuer = CompanySettings::query()->lockForUpdate()->first();
            $lockedProject = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $existing = $protocol->exists;
            if ($existing) {
                $protocol->refresh();
                abort_if($protocol->revision !== (int) $revision, 409, 'Protokół zmieniono w innym oknie. Odśwież formularz.');
            }
            $supplier = $client ? null : Company::findOrFail($data['supplier_company_id']);
            $clientSnapshot = $existing ? $protocol->client_snapshot : null;
            if ($client && ! $existing) {
                $customer = $lockedProject->company;
                if (! $customer) {
                    throw ValidationException::withMessages(['reference' => 'Przypisz klienta w danych projektu, aby zapisać protokół odbioru u klienta.']);
                }
                $clientSnapshot = $customer->only(['name', 'address', 'city', 'nip']);
            }
            $snapshot = $existing ? $protocol->issuer_snapshot : [
                'name' => $issuer?->name ?: config('app.name'), 'address' => $issuer?->address, 'city' => $issuer?->city, 'postcode' => $issuer?->postcode, 'nip' => $issuer?->nip,
                'logo' => $issuer && ($issuer->logo_data || ($issuer->logo_path && Storage::disk('public')->exists($issuer->logo_path))) ? $issuer->logoDataUri() : null,
                'color' => $issuer?->primaryColor() ?? '#1A4D3A',
                'project_number' => $project->number, 'project_name' => $project->name,
            ];
            $supplierSnapshot = ! $supplier ? [] : ($existing && $protocol->supplier_company_id === $supplier->id
                ? $protocol->supplier_snapshot : $supplier->only(['name', 'address', 'city', 'nip']));
            $protocol->fill($data + ['project_id' => $project->id, 'issuer_snapshot' => $snapshot, 'supplier_snapshot' => $supplierSnapshot,
                'client_snapshot' => $clientSnapshot,
                'signature_data' => $signature, 'revision' => $existing ? $protocol->revision + 1 : 1]);
            if (! $existing) {
                $protocol->created_by = $request->user()->id;
                $prefix = ($client ? 'POK/' : 'PO/').$project->protocolProjectReference().'/'.now()->format('Y').'/';
                $sequence = (int) (ProjectProtocol::withTrashed()->where('number', 'like', $prefix.'%')->pluck('number')
                    ->map(fn (string $number): int => (int) substr($number, strlen($prefix)))->max() ?? 0) + 1;
                $protocol->number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            }
            $protocol->save();
        });

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'protocols'])->with('success', 'Protokół został zapisany.');
    }

    public function destroy(Project $project, ProjectProtocol $protocol)
    {
        $this->access($project, $protocol, true);
        $protocol->delete();

        return redirect()->route('projects.show', ['project' => $project, 'tab' => 'protocols'])
            ->with('success', 'Protokół został usunięty.');
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
