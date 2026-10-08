<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentQuotaService
{
    public function usedMany(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }
        $query = DB::table('documents')->whereIn('storage_owner_id', $userIds)->select('storage_owner_id', 'size');
        foreach (['iso_section_documents', 'cylinder_videos', 'cylinder_photos', 'company_reliability_reports', 'company_reliability_files'] as $table) {
            $query->unionAll(DB::table($table)->whereIn('storage_owner_id', $userIds)->select('storage_owner_id', 'size'));
        }

        return DB::query()->fromSub($query, 'stored_files')
            ->selectRaw('storage_owner_id, SUM(size) AS total')
            ->groupBy('storage_owner_id')->pluck('total', 'storage_owner_id')
            ->map(fn ($bytes) => (int) $bytes);
    }

    public function used(int $userId): int
    {
        return (int) $this->usedMany([$userId])->get($userId, 0);
    }
}
