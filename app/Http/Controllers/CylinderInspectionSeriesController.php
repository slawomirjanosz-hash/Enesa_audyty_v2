<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Cylinder;
use App\Models\CylinderInspection;
use App\Models\CylinderInspectionSeries;
use App\Models\CylinderInspectionSeriesItem;
use App\Models\User;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CylinderInspectionSeriesController extends Controller
{
    private function staff()
    {
        return User::whereHas('roles', fn ($q) => $q->whereNotIn('name', ['client_admin', 'client_user']));
    }

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::in(array_keys(CylinderInspectionSeries::STATUSES))], 'q' => ['nullable', 'string', 'max:160']]);
        $query = CylinderInspectionSeries::with(['company', 'responsible'])->withCount(['items', 'items as saved_count' => fn ($q) => $q->whereNotNull('inspection_id')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->latest('id');
        TableSort::apply($query, $request, [
            'name' => 'name', 'company' => Company::select('name')->whereColumn('companies.id', 'cylinder_inspection_series.company_id'),
            'responsible' => User::select('name')->whereColumn('users.id', 'cylinder_inspection_series.responsible_id'),
            'status' => 'status', 'saved' => 'saved_count', 'total' => 'items_count', 'created' => 'created_at',
        ]);

        return view('cylinders.series.index', ['seriesList' => $query->paginate(30)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $data = $request->validate(['cylinder_ids' => ['required', 'array', 'min:1', 'max:300'], 'cylinder_ids.*' => ['required', 'integer', 'distinct', Rule::exists('cylinders', 'id')->whereNull('archived_at')]]);

        return view('cylinders.series.create', [
            'cylinders' => Cylinder::with('company')->whereIn('id', $data['cylinder_ids'])->orderBy('name')->get(),
            'companies' => Company::clients()->active()->orderBy('name')->get(['id', 'name']),
            'staff' => $this->staff()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('company_type', 'client')->whereNull('archived_at')],
            'responsible_id' => ['required', 'integer'],
            'cylinder_ids' => ['required', 'array', 'min:1', 'max:300'],
            'cylinder_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        if (! $this->staff()->whereKey($data['responsible_id'])->exists()) {
            throw ValidationException::withMessages(['responsible_id' => 'Wybierz osobę z zespołu.']);
        }
        $series = DB::transaction(function () use ($data, $request) {
            $cylinders = Cylinder::whereIn('id', $data['cylinder_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($cylinders->count() !== count($data['cylinder_ids']) || $cylinders->contains(fn ($cylinder) => $cylinder->archived_at !== null)) {
                throw ValidationException::withMessages(['cylinder_ids' => 'Wybierz istniejące, aktywne urządzenia.']);
            }
            if (! empty($data['company_id']) && $cylinders->contains(fn ($cylinder) => (int) $cylinder->company_id !== (int) $data['company_id'])) {
                throw ValidationException::withMessages(['company_id' => 'Wszystkie urządzenia muszą być przypisane do wybranego klienta. Możesz zapisać serię bez klienta.']);
            }
            $series = CylinderInspectionSeries::create(collect($data)->except('cylinder_ids')->all() + ['created_by' => $request->user()->id]);
            $series->items()->createMany($cylinders->map(fn ($cylinder) => ['cylinder_id' => $cylinder->id])->all());

            return $series;
        });

        return redirect()->route('cylinders.series.show', $series)->with('success', 'Zapisano serię. Możesz rozpocząć przeglądy lub wrócić do niej później.');
    }

    public function show(Request $request, CylinderInspectionSeries $series)
    {
        $series->load(['company', 'responsible'])->loadCount(['items', 'items as saved_count' => fn ($q) => $q->whereNotNull('inspection_id')]);
        $items = $series->items()->with(['cylinder.company', 'inspection'])->orderBy('id');
        TableSort::apply($items->getQuery(), $request, [
            'name' => Cylinder::select('name')->whereColumn('cylinders.id', 'cylinder_inspection_series_items.cylinder_id'),
            'mark' => Cylinder::select('manufacturer_mark')->whereColumn('cylinders.id', 'cylinder_inspection_series_items.cylinder_id'),
            'serial' => Cylinder::select('serial_number')->whereColumn('cylinders.id', 'cylinder_inspection_series_items.cylinder_id'),
            'status' => 'inspection_id',
            'date' => CylinderInspection::select('inspected_at')->whereColumn('cylinder_inspections.id', 'cylinder_inspection_series_items.inspection_id'),
        ]);

        return view('cylinders.series.show', ['series' => $series, 'items' => $items->paginate(30)->withQueryString(), 'nextItem' => $series->items()->whereNull('inspection_id')->whereHas('cylinder', fn ($q) => $q->whereNull('archived_at'))->orderBy('id')->first()]);
    }

    public function inspect(CylinderInspectionSeries $series, CylinderInspectionSeriesItem $item)
    {
        abort_unless((int) $item->series_id === (int) $series->id, 404);
        abort_if($series->status === 'completed' || $item->inspection_id, 409, 'Ten przegląd jest już zapisany lub seria jest zakończona.');
        $cylinder = $item->cylinder;
        abort_if($cylinder->archived_at, 409, 'Przywróć urządzenie z archiwum przed przeglądem.');

        return view('cylinders.series.inspect', ['series' => $series, 'item' => $item, 'cylinder' => $cylinder, 'results' => CylinderInspection::RESULTS]);
    }

    public function complete(CylinderInspectionSeries $series)
    {
        DB::transaction(function () use ($series) {
            $locked = CylinderInspectionSeries::lockForUpdate()->findOrFail($series->id);
            abort_if($locked->status === 'completed', 409, 'Seria została już zakończona.');
            if (! $locked->items()->exists() || $locked->items()->whereNull('inspection_id')->exists()) {
                throw ValidationException::withMessages(['series' => 'Zapisz przegląd każdego urządzenia przed zakończeniem serii.']);
            }
            $locked->update(['status' => 'completed', 'completed_at' => now()]);
        });

        return redirect()->route('cylinders.series.show', $series)->with('success', 'Seria została zakończona. Wyniki są zachowane w historii urządzeń.');
    }
}
