<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definition = json_decode(file_get_contents(resource_path('iso50001/plant-profile-v2.json')), true, 512, JSON_THROW_ON_ERROR);
        // IDs only: never sort full JSON records in MySQL.
        $ids = DB::table('iso_plant_profiles')->whereNotExists(function ($query) {
            $query->selectRaw('1')->from('iso_plant_profiles as newer')
                ->whereColumn('newer.audit_id', 'iso_plant_profiles.audit_id')
                ->whereColumn('newer.site_id', 'iso_plant_profiles.site_id')
                ->whereColumn('newer.revision', '>', 'iso_plant_profiles.revision');
        })->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $definition) {
                $profile = DB::table('iso_plant_profiles')->where('id', $id)->lockForUpdate()->first();
                $previous = json_decode($profile->definition, true, 512, JSON_THROW_ON_ERROR);
                if (($previous['version'] ?? '') === '2.0') {
                    return;
                }
                DB::table('iso_plant_events')->insert([
                    'profile_id' => $id, 'user_id' => null, 'user_name' => 'System', 'action' => 'schema_upgrade',
                    'snapshot' => json_encode((array) $profile, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $next = $definition;
                $next['legacy_groups'] = $previous['groups'];
                if ($profile->document_id) {
                    DB::table('iso_section_documents')->where('id', $profile->document_id)->update(['description' => 'Dokument historyczny — profil sprzed rozszerzenia ankiety według biblioteki audytorów.']);
                }
                DB::table('iso_plant_profiles')->where('id', $id)->update([
                    'definition' => json_encode($next, JSON_THROW_ON_ERROR), 'status' => 'editing',
                    'client_approval' => null, 'auditor_approval' => null, 'document_id' => null, 'issuer' => null,
                    'auditor_changes' => null, 'client_changes' => null, 'review_note' => null,
                    'lock_version' => $profile->lock_version + 1, 'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        // Do not erase answers entered after the upgrade. Original snapshots remain in history.
    }
};
