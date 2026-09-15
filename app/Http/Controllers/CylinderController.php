<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Cylinder;
use App\Models\CylinderInspection;
use App\Models\CylinderVideo;
use App\Models\User;
use App\Services\DocumentQuotaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $query = $this->query($request)->with(['company', 'latestInspection']);
        $query->when($request->boolean('archived'), fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'));
        if ($search = trim($data['q'] ?? '')) {
            $query->where(fn ($q) => $q->where('serial_number', 'like', '%'.$search.'%')->orWhere('type', 'like', '%'.$search.'%')->orWhereHas('company', fn ($c) => $c->where('name', 'like', '%'.$search.'%')));
        }

        return view('cylinders.index', $this->viewData($request) + ['cylinders' => $query->orderBy('serial_number')->paginate(30)->withQueryString()]);
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

        return view('cylinders.show', $this->viewData($request) + [
            'cylinder' => $cylinder->load(['company', 'latestInspection']),
            'videos' => $cylinder->videos()->latest()->paginate(12, ['*'], 'videos_page'),
            'inspections' => $cylinder->inspections()->orderByDesc('inspected_at')->orderByDesc('id')->paginate(20),
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
            'file' => ['required', 'file', 'max:102400', 'mimetypes:video/mp4,video/webm', 'extensions:mp4,webm'],
        ]);
        $file = $request->file('file');
        $path = null;
        try {
            DB::transaction(function () use ($request, $cylinder, $data, $file, &$path): void {
                $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
                abort_if($locked->archived_at, 409, 'Przywróć butlę z archiwum przed dodaniem filmu.');
                User::query()->lockForUpdate()->findOrFail($request->user()->id);
                app(DocumentQuotaService::class)->assertAdditional($request->user()->id, $file->getSize());
                $path = $file->store('cylinder-videos/'.$cylinder->id, 'local');
                abort_unless($path, 500, 'Nie udało się zapisać filmu.');
                $locked->videos()->create([
                    'title' => $data['title'], 'stored_path' => $path, 'mime_type' => $file->getMimeType(),
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
        $data = $request->validate([
            'inspected_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'next_due_at' => ['nullable', 'date_format:Y-m-d', 'after:inspected_at'],
            'result' => ['required', Rule::in(array_keys(CylinderInspection::RESULTS))],
            'observations' => ['required', 'string', 'max:20000'],
        ]);
        DB::transaction(function () use ($request, $cylinder, $data): void {
            $locked = Cylinder::query()->lockForUpdate()->findOrFail($cylinder->id);
            abort_if($locked->archived_at, 409, 'Przywróć butlę z archiwum przed zapisaniem przeglądu.');
            $locked->inspections()->create($data + ['inspector_id' => $request->user()->id, 'inspector_name' => $request->user()->name]);
        });

        return redirect()->route('cylinders.show', $cylinder)->with('success', 'Zapisano przegląd. Wpis pozostaje w historii bez możliwości nadpisania.');
    }
}
