<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\WarehouseDocument;
use App\Models\WarehouseItem;
use App\Services\AuditorAccessService;
use App\Services\WarehouseService;
use App\Support\TableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseController extends Controller
{
    private function itemsQuery(Request $request): Builder
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'category' => ['nullable', 'string', 'max:100']]);
        $query = WarehouseItem::query()->where('is_active', ! $request->boolean('archived'));
        if ($term = trim($data['q'] ?? '')) {
            $query->where(fn ($q) => $q->where('sku', 'like', "%$term%")->orWhere('name', 'like', "%$term%")->orWhere('location', 'like', "%$term%"));
        }
        if ($data['category'] ?? null) {
            $query->where('category', $data['category']);
        }
        if ($request->boolean('low')) {
            $query->whereColumn('quantity', '<=', 'minimum_stock');
        }

        return $query;
    }

    private function sort(Builder $query, Request $request, array $columns, string $default): Builder
    {
        $query->orderBy($default, $default === 'id' ? 'desc' : 'asc')->orderBy('id');

        return TableSort::apply($query, $request, $columns);
    }

    public function index(Request $request): View
    {
        $query = $this->itemsQuery($request)->select('warehouse_items.*')->selectRaw('quantity * unit_cost as stock_value');
        $this->sort($query, $request, array_combine(['sku', 'name', 'category', 'location', 'unit', 'quantity', 'minimum_stock', 'unit_cost', 'stock_value'], ['sku', 'name', 'category', 'location', 'unit', 'quantity', 'minimum_stock', 'unit_cost', 'stock_value']), 'name');

        return view('warehouse.index', [
            'items' => $query->paginate(30)->withQueryString(),
            'categories' => WarehouseItem::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'totals' => WarehouseItem::where('is_active', true)->selectRaw('COUNT(*) as count, COALESCE(SUM(quantity * unit_cost),0) as value, COALESCE(SUM(CASE WHEN quantity <= minimum_stock THEN 1 ELSE 0 END),0) as low')->first(),
        ]);
    }

    public function create(): View
    {
        return view('warehouse.item-form', ['item' => new WarehouseItem]);
    }

    public function show(WarehouseItem $item): View
    {
        return view('warehouse.item', ['item' => $item]);
    }

    public function edit(WarehouseItem $item): View
    {
        return view('warehouse.item-form', compact('item'));
    }

    private function itemData(Request $request, ?WarehouseItem $item = null): array
    {
        if (is_string($request->input('sku'))) {
            $request->merge(['sku' => Str::upper(trim($request->input('sku')))]);
        }

        return $request->validate([
            'sku' => ['required', 'string', 'max:80', Rule::unique('warehouse_items')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:200'], 'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'], 'unit' => ['required', 'string', Rule::in(['szt.', 'kpl.', 'm', 'm²', 'm³', 'kg', 'l', 'opak.'])],
            'description' => ['nullable', 'string', 'max:5000'], 'minimum_stock' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,3'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = WarehouseItem::create($this->itemData($request));

        return redirect()->route('warehouse.items.show', $item)->with('success', 'Pozycja dodana. Zwiększ stan dokumentem przyjęcia.');
    }

    public function update(Request $request, WarehouseItem $item): RedirectResponse
    {
        $data = $this->itemData($request, $item);
        $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($item, $request, $data) {
            $locked = WarehouseItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($locked->revision !== $request->integer('revision')) {
                throw ValidationException::withMessages(['revision' => 'Pozycja została zmieniona. Odśwież formularz przed zapisem.']);
            }
            if ($data['unit'] !== $locked->unit && $locked->lines()->exists()) {
                throw ValidationException::withMessages(['unit' => 'Nie można zmienić jednostki po zapisaniu ruchów magazynowych.']);
            }
            $locked->fill($data);
            $locked->revision++;
            $locked->save();
        });

        return redirect()->route('warehouse.items.show', $item)->with('success', 'Zapisano dane pozycji.');
    }

    public function archive(Request $request, WarehouseItem $item): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($item, $data) {
            $locked = WarehouseItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if (! $data['is_active'] && WarehouseService::scaled($locked->quantity, 3) !== 0) {
                throw ValidationException::withMessages(['is_active' => 'Najpierw rozlicz stan do zera. Nie można archiwizować towaru ze stanem.']);
            }
            $locked->is_active = (bool) $data['is_active'];
            $locked->revision++;
            $locked->save();
        });

        return back()->with('success', 'Zmieniono dostępność pozycji. Historia pozostaje zachowana.');
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $term = trim($request->string('q')->toString());
        $items = WarehouseItem::where('is_active', true)
            ->where(fn ($q) => $q->where('sku', 'like', "%$term%")->orWhere('name', 'like', "%$term%"))
            ->orderByRaw('CASE WHEN sku = ? THEN 0 ELSE 1 END', [$term])->orderBy('name')->limit(25)
            ->get(['id', 'sku', 'name', 'quantity', 'unit', 'unit_cost', 'revision']);

        return response()->json($items);
    }

    private function documentPermission(string $type): string
    {
        abort_unless(isset(WarehouseDocument::TYPES[$type]), 404);

        return 'warehouse.'.['receipt' => 'receive', 'issue' => 'issue', 'adjustment' => 'adjust'][$type];
    }

    private function authorizeDocument(Request $request, string $type): void
    {
        $permission = $this->documentPermission($type);
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()) || $request->user()->can($permission), 403);
    }

    private function projects(Request $request): Builder
    {
        $query = Project::query();
        if (! app(AuditorAccessService::class)->hasFullAccess($request->user())) {
            $query->where(fn ($q) => $q->where('manager_id', $request->user()->id)->orWhereHas('members', fn ($m) => $m->where('users.id', $request->user()->id)));
            if (! $request->user()->can('projects.view')) {
                $query->whereRaw('1=0');
            }
        }
        if (! CompanySettings::moduleIsEnabled('projects')) {
            $query->whereRaw('1=0');
        }

        return $query;
    }

    private function suppliers(Request $request): Builder
    {
        $query = Company::suppliers()->active();
        app(AuditorAccessService::class)->scopeByCompanyAccess($query, $request->user(), 'can_view_dashboard', 'id');
        if (! CompanySettings::moduleIsEnabled('crm')) {
            $query->whereRaw('1=0');
        }

        return $query;
    }

    public function createDocument(Request $request, string $type): View
    {
        $this->authorizeDocument($request, $type);
        $item = $request->integer('item') ? WarehouseItem::where('is_active', true)->findOrFail($request->integer('item')) : null;
        $oldLines = old('lines');
        $formLines = is_array($oldLines) ? collect($oldLines)->filter(fn ($line) => is_array($line))->take(50)->map(function ($line) {
            return collect($line)->only(['item_id', 'quantity', 'unit_cost', 'revision'])->map(fn ($value) => is_scalar($value) ? $value : '')->all();
        })->values()->all() : [];
        if ($formLines === []) {
            $formLines = [['item_id' => $item?->id, 'quantity' => '', 'unit_cost' => '', 'revision' => $item?->revision]];
        }
        $ids = collect($formLines)->pluck('item_id')->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id))->all();
        if ($item) {
            $ids[] = $item->id;
        }

        return view('warehouse.document-form', [
            'type' => $type, 'item' => $item, 'selectedItems' => WarehouseItem::whereIn('id', $ids)->get()->keyBy('id'),
            'formLines' => $formLines,
            'token' => (string) Str::uuid(),
            'projects' => $type === 'issue' ? $this->projects($request)->orderBy('number')->get(['id', 'number', 'name']) : collect(),
            'suppliers' => $type === 'receipt' ? $this->suppliers($request)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function storeDocument(Request $request, WarehouseService $service): RedirectResponse
    {
        $request->validate(['type' => ['required', Rule::in(array_keys(WarehouseDocument::TYPES))]]);
        $type = $request->string('type')->toString();
        $this->authorizeDocument($request, $type);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(WarehouseDocument::TYPES))],
            'submission_token' => ['required', 'uuid'], 'document_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['required', 'string', 'max:3000'], 'reference' => ['nullable', 'string', 'max:200'],
            'project_id' => [$type === 'issue' ? 'nullable' : 'prohibited', 'integer'],
            'supplier_id' => [$type === 'receipt' ? 'nullable' : 'prohibited', 'integer'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', 'exists:warehouse_items,id'],
            'lines.*.quantity' => ['required', 'numeric', $type === 'adjustment' ? 'min:0' : 'min:0.001', 'max:1000000', 'decimal:0,3'],
            'lines.*.unit_cost' => [$type === 'receipt' ? 'required' : 'exclude', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'lines.*.revision' => [$type === 'adjustment' ? 'required' : 'exclude', 'integer', 'min:1'],
        ]);
        $project = ! empty($data['project_id']) ? $this->projects($request)->findOrFail($data['project_id']) : null;
        $supplier = ! empty($data['supplier_id']) ? $this->suppliers($request)->findOrFail($data['supplier_id']) : null;
        $document = $service->post($data, $request->user(), $project, $supplier);

        return redirect()->route('warehouse.documents.show', $document)->with('success', 'Dokument zapisany. Stany magazynowe zostały rozliczone.');
    }

    public function documents(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'type' => ['nullable', Rule::in(array_keys(WarehouseDocument::TYPES))], 'item' => ['nullable', 'integer']]);
        $query = WarehouseDocument::query()->select('warehouse_documents.*')->selectRaw("CASE type WHEN 'receipt' THEN 'Przyjęcie (PZ)' WHEN 'issue' THEN 'Wydanie (WZ)' ELSE 'Inwentaryzacja / korekta (KOR)' END as type_label");
        if ($data['q'] ?? null) {
            $term = '%'.$data['q'].'%';
            $query->where(fn ($q) => $q->where('number', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('project_label', 'like', $term)->orWhere('supplier_name', 'like', $term));
        }
        if ($data['type'] ?? null) {
            $query->where('type', $data['type']);
        }
        if ($data['item'] ?? null) {
            $query->whereHas('lines', fn ($q) => $q->where('warehouse_item_id', $data['item']));
        }
        $columns = ['number', 'type', 'document_date', 'author_name', 'project_label', 'supplier_name', 'reference', 'created_at'];
        $sortColumns = array_combine($columns, $columns);
        $sortColumns['type'] = 'type_label';
        $this->sort($query, $request, $sortColumns, 'id');

        return view('warehouse.documents', ['documents' => $query->paginate(30)->withQueryString()]);
    }

    public function showDocument(WarehouseDocument $document): View
    {
        return view('warehouse.document', ['document' => $document->load('lines')]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->itemsQuery($request)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Kod', 'Nazwa', 'Kategoria', 'Lokalizacja', 'Jednostka', 'Stan', 'Minimum', 'Cena ewidencyjna netto PLN', 'Wartość netto PLN'], ';');
            foreach ($query->lazyById(500) as $item) {
                $row = [$item->sku, $item->name, $item->category, $item->location, $item->unit, $item->quantity, $item->minimum_stock, $item->unit_cost, number_format((float) $item->quantity * (float) $item->unit_cost, 2, '.', '')];
                $row = array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $row);
                fputcsv($stream, $row, ';');
            }
            fclose($stream);
        }, 'magazyn-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
