<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\CylinderInspection;
use App\Models\CylinderInspectionSeries;
use App\Models\CylinderProtocol;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CylinderProtocolService
{
    public const VERSION = '1.0';

    public function snapshot(CylinderInspectionSeries $series): array
    {
        $series->load(['company', 'responsible', 'items' => fn ($q) => $q->orderBy('id'), 'items.cylinder.company', 'items.inspection.photos', 'items.inspection.videos']);
        $brand = CompanySettings::first();

        return [
            'template_version' => self::VERSION,
            'date' => today()->format('Y-m-d'),
            'issuer' => $brand?->only(['name', 'address', 'postcode', 'city', 'nip', 'email', 'phone']) ?? ['name' => 'ENESA'],
            'color' => $brand?->primaryColor() ?? '#1A4D3A', 'logo' => $brand?->logoDataUri(),
            'series' => ['id' => $series->id, 'name' => $series->name, 'status' => $series->status, 'responsible' => $series->responsible->name],
            'client' => $series->company?->only(['name', 'address', 'city', 'nip']),
            'items' => $series->items->map(fn ($item) => [
                'device_id' => $item->cylinder_id,
                'parameters' => collect(Cylinder::PARAMETER_LABELS)->mapWithKeys(fn ($label, $field) => [$field => $item->cylinder->$field])->all(),
                'client' => $item->cylinder->company?->only(['name', 'address', 'city', 'nip']),
                'inspection' => $item->inspection ? $item->inspection->only(['id', 'revision', 'result', 'observations', 'inspector_name', 'weight_kg', 'working_pressure_bar']) + [
                    'inspected_at' => $item->inspection->inspected_at->format('Y-m-d'),
                    'next_due_at' => $item->inspection->next_due_at?->format('Y-m-d'),
                    'photos' => $item->inspection->photos->map(fn ($photo) => ['id' => $photo->id])->all(),
                    'videos' => $item->inspection->videos->map(fn ($video) => ['id' => $video->id, 'title' => $video->title])->all(),
                ] : null,
            ])->all(),
        ];
    }

    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function render(array $snapshot, ?string $number = null): string
    {
        return Pdf::loadView('cylinders.protocols.pdf', compact('snapshot', 'number'))
            ->setPaper('a4')->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->output();
    }

    public function issue(CylinderInspectionSeries $series, User $user, string $fingerprint): CylinderProtocol
    {
        $path = null;
        try {
            return DB::transaction(function () use ($series, $user, $fingerprint, &$path) {
                $series = CylinderInspectionSeries::lockForUpdate()->findOrFail($series->id);
                if ($existing = $series->protocol()->first()) {
                    return $existing;
                }
                if ($series->status !== 'completed' || ! $series->items()->exists() || $series->items()->whereNull('inspection_id')->exists()) {
                    throw ValidationException::withMessages(['protocol' => 'Zakończ serię i zapisz wszystkie przeglądy przed wydaniem protokołu.']);
                }
                // Match inspection edits' cylinder -> inspection locking order.
                $items = $series->items()->orderBy('id')->get();
                Cylinder::whereIn('id', $items->pluck('cylinder_id'))->orderBy('id')->lockForUpdate()->get();
                CylinderInspection::whereIn('id', $items->pluck('inspection_id'))->orderBy('id')->lockForUpdate()->get();
                $snapshot = $this->snapshot($series);
                if (! hash_equals($this->fingerprint($snapshot), $fingerprint)) {
                    throw ValidationException::withMessages(['protocol' => 'Dane zmieniły się od otwarcia podglądu. Otwórz podgląd ponownie przed wydaniem.']);
                }
                $number = 'PR/'.now()->year.'/'.str_pad((string) $series->id, 6, '0', STR_PAD_LEFT);
                $contents = $this->render($snapshot, $number);
                $path = 'cylinder-protocols/'.$series->id.'/'.Str::uuid().'.pdf';
                abort_unless(Storage::disk('local')->put($path, $contents), 500, 'Nie udało się zapisać protokołu.');

                return CylinderProtocol::create([
                    'series_id' => $series->id, 'number' => $number, 'issued_by' => $user->id, 'issued_at' => now(),
                    'template_version' => self::VERSION, 'snapshot' => $snapshot,
                    'stored_path' => $path, 'sha256' => hash('sha256', $contents),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
