<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CylinderImport;
use App\Models\User;
use App\Services\CylinderImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CylinderImportController extends Controller
{
    public function create()
    {
        return view('cylinders.import.create', [
            'companies' => Company::clients()->active()->orderBy('name')->get(['id', 'name']),
            'staff' => User::whereHas('roles', fn ($q) => $q->whereNotIn('name', ['client_admin', 'client_user']))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function template()
    {
        return response()->download(resource_path('templates/cylinder-import.xlsx'), 'szablon-urzadzen.xlsx', ['Cache-Control' => 'private, no-store']);
    }

    public function preview(Request $request, CylinderImportService $service)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'extensions:xlsx,xls', 'max:5120'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('company_type', 'client')->whereNull('archived_at')],
            'create_series' => ['nullable', 'boolean'],
            'series_name' => ['required_if:create_series,1', 'nullable', 'string', 'max:160'],
            'responsible_id' => ['required_if:create_series,1', 'nullable', 'integer', Rule::exists('users', 'id')],
        ]);
        if ($request->boolean('create_series') && ! User::whereKey($data['responsible_id'])->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['client_admin', 'client_user']))->exists()) {
            throw ValidationException::withMessages(['responsible_id' => 'Wybierz osobę z zespołu.']);
        }
        $rows = $service->parse($request->file('file'), isset($data['company_id']) ? (int) $data['company_id'] : null);
        $import = CylinderImport::create([
            'token' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'company_id' => $data['company_id'] ?? null,
            'rows' => $rows, 'create_series' => $request->boolean('create_series'),
            'series_name' => $data['series_name'] ?? null, 'responsible_id' => $data['responsible_id'] ?? null,
            'expires_at' => now()->addDay(),
        ]);

        return redirect()->route('cylinders.import.show', $import);
    }

    private function owned(Request $request, string $token): CylinderImport
    {
        return CylinderImport::where('token', $token)->where('user_id', $request->user()->id)->firstOrFail();
    }

    public function show(Request $request, string $token, CylinderImportService $service)
    {
        $import = $this->owned($request, $token);
        abort_if(! $import->completed_at && $import->expires_at->isPast(), 410, 'Podgląd importu wygasł. Wczytaj plik ponownie.');

        return view('cylinders.import.preview', ['import' => $import, 'columns' => $service->columns(), 'company' => Company::find($import->company_id)]);
    }

    public function store(Request $request, string $token, CylinderImportService $service)
    {
        $import = $service->commit($this->owned($request, $token));

        return redirect()->route($import->series_id ? 'cylinders.series.show' : 'cylinders.index', $import->series_id ? [$import->series_id] : [])
            ->with('success', 'Import zapisany. Istniejące urządzenia i ich historia pozostały bez zmian.'.($import->series_id ? ' Utworzono serię roboczą.' : ''));
    }
}
