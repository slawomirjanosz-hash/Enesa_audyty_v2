<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ArchivedCompanyDeletion
{
    private const FILE_COLUMNS = [
        'documents' => ['stored_path'], 'iso_section_documents' => ['stored_path'],
        'company_reliability_reports' => ['stored_path'], 'company_reliability_files' => ['stored_path'],
        'cylinder_videos' => ['stored_path'], 'cylinder_photos' => ['stored_path', 'thumbnail_path'],
        'cylinder_protocols' => ['stored_path'], 'companies' => ['logo_path'], 'company_settings' => ['logo_path'],
    ];

    public function purge(Company $company): void
    {
        DB::transaction(function () use ($company) {
            $company = Company::lockForUpdate()->findOrFail($company->id);
            if (! $company->archived_at || $company->is_owner) {
                throw ValidationException::withMessages(['company' => 'Usunąć można tylko zarchiwizowaną firmę klienta lub dostawcy.']);
            }
            $this->purgeHistory($company);
        });
    }

    private function purgeHistory(Company $company): void
    {
        $projectIds = DB::table('projects')->where('company_id', $company->id)->lockForUpdate()->pluck('id');
        $auditIds = DB::table('audits')->where('company_id', $company->id)->lockForUpdate()->pluck('id');
        $offerIds = DB::table('offers')->where('company_id', $company->id)->lockForUpdate()->pluck('id');
        $cylinderIds = DB::table('cylinders')->where('company_id', $company->id)->lockForUpdate()->pluck('id');
        $seriesIds = DB::table('cylinder_inspection_series')->where('company_id', $company->id)->lockForUpdate()->pluck('id');
        // Do not remove records belonging to a different company just because
        // this company is its supplier or a device was assigned inconsistently.
        if (DB::table('project_protocols')->where('supplier_company_id', $company->id)->whereNotIn('project_id', $projectIds)->exists()
            || DB::table('cylinder_inspection_series_items')->whereIn('cylinder_id', $cylinderIds)->whereNotIn('series_id', $seriesIds)->exists()) {
            throw ValidationException::withMessages(['company' => 'Firma jest powiązana z chronionymi dokumentami lub seriami innej firmy. Najpierw wyjaśnij te powiązania. Dane nie zostały usunięte.']);
        }
        $documents = DB::table('documents')->where('company_id', $company->id)->orWhere(fn ($query) => $query->whereNull('company_id')->where(fn ($owned) => $owned->whereIn('project_id', $projectIds)->orWhereIn('audit_id', $auditIds)->orWhereIn('offer_id', $offerIds)));
        $files = collect();
        $sources = [
            'documents' => clone $documents,
            'iso_section_documents' => DB::table('iso_section_documents')->whereIn('audit_id', $auditIds),
            'company_reliability_reports' => DB::table('company_reliability_reports')->where('company_id', $company->id),
            'company_reliability_files' => DB::table('company_reliability_files')->where('company_id', $company->id),
            'cylinder_videos' => DB::table('cylinder_videos')->whereIn('cylinder_id', $cylinderIds),
            'cylinder_photos' => DB::table('cylinder_photos')->whereIn('cylinder_id', $cylinderIds),
            'cylinder_protocols' => DB::table('cylinder_protocols')->whereIn('series_id', $seriesIds),
            'companies' => DB::table('companies')->where('id', $company->id),
        ];
        foreach ($sources as $table => $query) {
            foreach ($query->lockForUpdate()->get(self::FILE_COLUMNS[$table]) as $row) {
                foreach (self::FILE_COLUMNS[$table] as $column) {
                    if ($row->$column) {
                        $files->push($row->$column);
                    }
                }
            }
        }
        $documents->delete();
        DB::table('cylinder_imports')->where('company_id', $company->id)->orWhereIn('series_id', $seriesIds)->delete();
        DB::table('cylinder_protocols')->whereIn('series_id', $seriesIds)->delete();
        DB::table('cylinder_inspection_series_items')->whereIn('series_id', $seriesIds)->delete();
        DB::table('cylinder_photos')->whereIn('cylinder_id', $cylinderIds)->delete();
        DB::table('cylinder_videos')->whereIn('cylinder_id', $cylinderIds)->delete();
        DB::table('cylinder_inspections')->whereIn('cylinder_id', $cylinderIds)->delete();
        DB::table('cylinders')->whereIn('id', $cylinderIds)->delete();
        DB::table('cylinder_inspection_series')->whereIn('id', $seriesIds)->delete();
        DB::table('tasks')->where('company_id', $company->id)->orWhereIn('project_id', $projectIds)->orWhereIn('audit_id', $auditIds)->delete();
        DB::table('crm_opportunities')->where('company_id', $company->id)->delete();
        DB::table('energy_passports')->where('company_id', $company->id)->delete();
        DB::table('projects')->whereIn('id', $projectIds)->delete();
        DB::table('audits')->whereIn('id', $auditIds)->delete();
        app(ActivityLogService::class)->recordModel($company, 'deleted');
        // Eloquent's Company deleting hook removes files before commit. Here all
        // files are cleaned only after the full database transaction succeeds.
        DB::table('companies')->where('id', $company->id)->delete();
        DB::afterCommit(function () use ($files) {
            $remaining = $files->unique()->values();
            foreach (self::FILE_COLUMNS as $table => $columns) {
                foreach ($columns as $column) {
                    foreach ($remaining->chunk(500) as $chunk) {
                        $shared = DB::table($table)->whereIn($column, $chunk)->pluck($column);
                        $remaining = $remaining->diff($shared);
                    }
                }
            }
            foreach ($remaining as $path) {
                try {
                    if (Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
                        throw new \RuntimeException('File cleanup failed');
                    }
                } catch (Throwable $error) {
                    Log::warning('archive.file_cleanup_failed', ['path_hash' => hash('sha256', $path), 'error_type' => get_class($error)]);
                }
            }
        });
    }
}
