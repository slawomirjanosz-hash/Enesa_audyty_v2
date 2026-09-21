<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Cylinder;
use App\Models\CylinderInspection;
use App\Models\CylinderVideo;
use App\Models\User;
use App\Services\CylinderPhotoRenderer;
use App\Services\DocumentQuotaService;
use App\Support\CylinderVideoLink;
use App\Support\TableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CylinderController extends Controller
{
    private function clientView(Request $request): bool
    {
        return $request->routeIs('client.cylinders.*', 'client-zone.cylinders.*');
    }

    private function query(Request $request): Builder
    {
        $query = Cylinder::query();
        if ($request->routeIs('client.cylinders.*')) {
            $query->whereIn('company_id', $request->user()->companies()->select('companies.id'));
        } elseif ($request->routeIs('client-zone.cylinders.*')) {
            $query->where('company_id', $request->session()->get('client_zone_company_id'));
        }

        return $query;
    }

    private function viewData(Request $request): array
    {
        $client = $this->clientView($request);

        return [
            'clientView' => $client,
            'layout' => $request->routeIs('client-zone.*') ? 'layouts.client-zone' : ($client ? 'layouts.client' : 'layouts.app'),
            'routePrefix' => $request->routeIs('client-zone.*') ? 'client-zone.cylinders.' : ($client ? 'client.cylinders.' : 'cylinders.'),
            'canManage' => ! $client && ($request->user()->hasRole('superadmin') || $request->user()->canAny(['system.full_access', 'cylinders.manage'])),
        ];
    }

    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'archived' => ['nullable', 'in:1']]);
        $query = $this->query($request)->with(['company', 'latestInspection', 'photo']);
        $query->when($request->boolean('archived'), fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'));
        if ($search = trim($data['q'] ?? '')) {
            $query->where(fn ($q) => $q->where('serial_number', 'like', '%'.$search.'%')->orWhere('type', 'like', '%'.$search.'%')->orWhereHas('company', fn ($c) => $c->where('name', 'like', '%'.$search.'%')));
        }

        $latest = fn ($column) => CylinderInspection::select($column)->whereColumn('cylinder_id', 'cylinders.id')->orderByDesc('inspected_at')->orderByDesc('id')->limit(1);
        $query->select('cylinders.*')->selectSub(
            CylinderInspection::query()->selectRaw("CASE WHEN result IN ('defects_found', 'further_review') THEN 'Problemy / wymaga oceny' WHEN next_due_at IS NULL THEN 'Brak oceny lub terminu' WHEN next_due_at < ? THEN 'Po terminie' WHEN next_due_at <= ? THEN 'Termin w ciągu miesiąca' WHEN result = 'no_findings' THEN 'Bez uwag — termin ważny' ELSE 'Brak oceny lub terminu' END", [today()->toDateString(), today()->addMonthNoOverflow()->toDateString()])
                ->whereColumn('cylinder_id', 'cylinders.id')->orderByDesc('inspected_at')->orderByDesc('id')->limit(1),
            'condition_sort'
        )->orderBy('serial_number');
        TableSort::apply($query, $request, [
            'serial' => 'serial_number', 'type' => 'type', 'status' => 'condition_sort',
            'company' => Company::select('name')->whereColumn('companies.id', 'cylinders.company_id')->limit(1),
            'last' => $latest('inspected_at'), 'due' => $latest('next_due_at'),
        ]);

        return view('cylinders.index', $this->viewData($request) + ['cylinders' => $query->paginate(30)->withQueryString()]);
    }

    public function create(Request $request): View
    {
        return view('cylinders.form', $this->viewData($request) + ['cylinder' => new Cylinder, 'companies' => Company::clients()->active()->orderBy('name')->get(['id', 'name'])]);
    }

    public function edit(Request $request, Cylinder $cylinder): View
    {
        return view('cylinders.form', $this->viewData($request) + ['cylinder' => $cylinder->load('company'), 'companies' => collect()]);
    }

    private function validated(Request $request, ?Cylinder $cylinder = null): array
    {
        return $request->validate([
            // Owner cannot be changed after creation: history must not move between clients.
            'company_id' => $cylinder ? ['prohibited'] : ['required', 'integer', Rule::exists('companies', 'id')->where('company_type', 'client')->whereNull('archived_at')],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('cylinders')->where('company_id', $cylinder?->company_id ?? $request->input('company_id'))->ignore($cylinder?->id)],
            'manufacturer' => ['nullable', 'string', 'max:160'],
            'type' => ['required', 'string', 'max:160'],
            'manufactured_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'capacity_litres' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'working_pressure_bar' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cylinder = Cylinder::create($this->validated($request));

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Butla została dodana.');
    }

    public function update(Request $request, Cylinder $cylinder): RedirectResponse
    {
        $cylinder->update($this->validated($request, $cylinder));

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Dane butli zostały zapisane.');
    }

    public function show(Request $request, Cylinder $cylinder): View
    {
        $this->query($request)->whereKey($cylinder->id)->firstOrFail();
        $request->validate(['inspection' => ['nullable', 'integer'], 'attach' => ['nullable', 'integer']]);
        foreach (['inspection', 'attach'] as $key) {
            if ($request->filled($key)) {
                $cylinder->inspections()->findOrFail($request->integer($key));
            }
        }

        $inspections = $cylinder->inspections()->withCount('videos')->orderByDesc('inspected_at')->orderByDesc('id');
        TableSort::apply($inspections->getQuery(), $request, [
            'date' => 'inspected_at', 'inspector' => 'inspector_name', 'result' => 'result',
            'notes' => 'observations', 'due' => 'next_due_at',
        ]);

        return view('cylinders.show', $this->viewData($request) + [
            'cylinder' => $cylinder->load(['company', 'latestInspection', 'photo']),
            'videos' => $cylinder->videos()->when($request->filled('inspection'), fn ($q) => $q->where('cylinder_inspection_id', $request->integer('inspection')))->latest()->paginate(12, ['*'], 'videos_page')->withQueryString(),
            'inspections' => $inspections->paginate(20)->withQueryString(),
            'videoInspection' => $request->filled('attach') ? $cylinder->inspections()->findOrFail($request->integer('attach')) : null,
            'results' => CylinderInspection::RESULTS,
        ]);
    }

    public function archive(Request $request, Cylinder $cylinder): RedirectResponse
    {
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        DB::transaction(function () use ($cylinder, $data): void {
            $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
            $locked->update(['archived_at' => $data['archived'] ? now() : null]);
        });

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Zmieniono status archiwizacji. Historia została zachowana.');
    }

    public function storeVideo(Request $request, Cylinder $cylinder): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'cylinder_inspection_id' => ['nullable', 'integer', Rule::exists('cylinder_inspections', 'id')->where('cylinder_id', $cylinder->id)],
            'source' => ['nullable', Rule::in(['file', 'link'])],
            'file' => ['required_without:external_url', 'prohibited_if:source,link', 'prohibits:external_url', 'nullable', 'file', 'max:102400', 'mimetypes:video/mp4,video/webm', 'extensions:mp4,webm'],
            'external_url' => ['required_if:source,link', 'prohibited_if:source,file', 'prohibits:file', 'nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if (! CylinderVideoLink::parse($value)) {
                    $fail('Podaj poprawny link HTTPS do filmu. Dla YouTube wybierz link do konkretnego filmu, a dla Dysku Google — link do pliku (nie folderu).');
                }
            }],
        ]);
        $file = $request->file('file');
        $path = null;
        try {
            DB::transaction(function () use ($request, $cylinder, $data, $file, &$path): void {
                $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
                abort_if($locked->archived_at, 409, 'Przywróć butlę z archiwum przed dodaniem filmu.');
                if (! empty($data['external_url'])) {
                    $locked->videos()->create([
                        'title' => $data['title'], 'external_url' => $data['external_url'],
                        'cylinder_inspection_id' => $data['cylinder_inspection_id'] ?? null,
                        'stored_path' => '', 'mime_type' => 'text/uri-list', 'size' => 0,
                        'storage_owner_id' => $request->user()->id,
                    ]);

                    return;
                }
                User::query()->lockForUpdate()->findOrFail($request->user()->id);
                app(DocumentQuotaService::class)->assertAdditional($request->user()->id, $file->getSize());
                $path = $file->store('cylinder-videos/'.$cylinder->id, 'local');
                abort_unless($path, 500, 'Nie udało się zapisać filmu.');
                $locked->videos()->create([
                    'title' => $data['title'], 'stored_path' => $path, 'mime_type' => $file->getMimeType(),
                    'cylinder_inspection_id' => $data['cylinder_inspection_id'] ?? null,
                    'size' => $file->getSize(), 'storage_owner_id' => $request->user()->id,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Film został dodany do butli.');
    }

    public function video(Request $request, Cylinder $cylinder, CylinderVideo $video)
    {
        $this->query($request)->whereKey($cylinder->id)->firstOrFail();
        abort_unless((int) $video->cylinder_id === (int) $cylinder->id, 404);
        abort_if($video->external_url || ! $video->stored_path, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($video->stored_path), 404, 'Plik filmu jest niedostępny.');

        // BinaryFileResponse supports byte ranges for seeking without loading the entire video.
        return response()->file($disk->path($video->stored_path), [
            'Content-Type' => $video->mime_type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeInspection(Request $request, Cylinder $cylinder): RedirectResponse
    {
        $data = $this->inspectionData($request);
        DB::transaction(function () use ($request, $cylinder, $data): void {
            $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
            abort_if($locked->archived_at, 409, 'Przywróć butlę z archiwum przed zapisaniem przeglądu.');
            $locked->inspections()->create($data + ['inspector_id' => $request->user()->id, 'inspector_name' => $request->user()->name]);
        });

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Zapisano przegląd.');
    }

    private function inspectionData(Request $request): array
    {
        return $request->validate([
            'inspected_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'next_due_at' => ['nullable', 'date_format:Y-m-d', 'after:inspected_at'],
            'result' => ['required', Rule::in(array_keys(CylinderInspection::RESULTS))],
            'observations' => ['required', 'string', 'max:20000'],
        ]);
    }

    public function editInspection(Request $request, Cylinder $cylinder, CylinderInspection $inspection): View
    {
        abort_unless((int) $inspection->cylinder_id === (int) $cylinder->id, 404);
        abort_if($cylinder->archived_at, 409, 'Przywróć butlę z archiwum przed edycją.');

        return view('cylinders.inspection-edit', $this->viewData($request) + ['cylinder' => $cylinder, 'inspection' => $inspection, 'results' => CylinderInspection::RESULTS]);
    }

    public function updateInspection(Request $request, Cylinder $cylinder, CylinderInspection $inspection): RedirectResponse
    {
        abort_unless((int) $inspection->cylinder_id === (int) $cylinder->id, 404);
        $data = $this->inspectionData($request);
        $version = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($cylinder, $inspection, $data, $version): void {
            $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
            abort_if($locked->archived_at, 409, 'Przywróć butlę z archiwum przed edycją.');
            $entry = $locked->inspections()->lockForUpdate()->findOrFail($inspection->id);
            abort_unless($entry->revision === (int) $version['revision'], 409, 'Wpis został zmieniony przez inną osobę. Otwórz edycję ponownie.');
            $entry->fill($data);
            $entry->revision++;
            $entry->save();
        });

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Zapisano zmiany wpisu. Poprzednie wartości odnotowano w historii zmian.');
    }

    public function storePhoto(Request $request, Cylinder $cylinder, CylinderPhotoRenderer $renderer): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000']]);
        $images = $renderer->render($request->file('photo'));
        $paths = [];
        $oldPaths = [];
        try {
            DB::transaction(function () use ($request, $cylinder, $images, &$paths, &$oldPaths): void {
                $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
                abort_if($locked->archived_at, 409, 'Przywróć butlę z archiwum przed zmianą zdjęcia.');
                User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $photo = $locked->photo;
                $size = strlen($images['image']) + strlen($images['thumbnail']);
                $credit = $photo && (int) $photo->storage_owner_id === $request->user()->id ? (int) $photo->size : 0;
                app(DocumentQuotaService::class)->assertAdditional($request->user()->id, $size - $credit);
                $base = 'cylinder-photos/'.$locked->id.'/'.Str::uuid();
                $paths = [$base.'.jpg', $base.'-thumb.jpg'];
                foreach (array_values($images) as $i => $bytes) {
                    abort_unless(Storage::disk('local')->put($paths[$i], $bytes), 500, 'Nie udało się zapisać zdjęcia.');
                }
                $oldPaths = $photo ? [$photo->stored_path, $photo->thumbnail_path] : [];
                $locked->photo()->updateOrCreate([], ['stored_path' => $paths[0], 'thumbnail_path' => $paths[1], 'size' => $size, 'storage_owner_id' => $request->user()->id]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
        Storage::disk('local')->delete($oldPaths);

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Zdjęcie butli zostało zapisane.');
    }

    public function photo(Request $request, Cylinder $cylinder)
    {
        $this->query($request)->whereKey($cylinder->id)->firstOrFail();
        $photo = $cylinder->photo()->firstOrFail();
        $path = $request->boolean('thumbnail') ? $photo->thumbnail_path : $photo->stored_path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
