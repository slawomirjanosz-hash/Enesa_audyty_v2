<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentQuotaService
{
    public function assertAdditional(int $userId, int $bytes): void
    {
        $user = User::whereKey($userId)->firstOrFail();
        if ($this->used($userId) + $bytes > $user->document_limit_bytes) {
            throw ValidationException::withMessages(['file' => 'Wybrane pliki przekraczają dostępne miejsce. Wybierz mniej plików lub poproś administratora o zwiększenie limitu.']);
        }
    }

    public function usedMany(array $userIds): Collection
    {
        $totals = collect();
        foreach (['documents', 'iso_section_documents'] as $table) {
            foreach (DB::table($table)->whereIn('storage_owner_id', $userIds)->selectRaw('storage_owner_id, SUM(size) AS total')->groupBy('storage_owner_id')->get() as $row) {
                $totals->put($row->storage_owner_id, (int) $totals->get($row->storage_owner_id, 0) + (int) $row->total);
            }
        }

        return $totals;
    }

    public function used(int $userId): int
    {
        return (int) DB::table('documents')->where('storage_owner_id', $userId)->sum('size')
            + (int) DB::table('iso_section_documents')->where('storage_owner_id', $userId)->sum('size');
    }
}
