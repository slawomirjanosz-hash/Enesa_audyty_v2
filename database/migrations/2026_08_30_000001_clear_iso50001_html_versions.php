<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $isoTypeId = DB::table('audit_types')->where('slug', 'iso50001')->value('id');
        if ($isoTypeId) {
            DB::table('audit_type_versions')->where('audit_type_id', $isoTypeId)->delete();
        }
    }

    public function down(): void
    {
        // Usuniętych historycznych plików HTML nie da się wiarygodnie odtworzyć.
    }
};
