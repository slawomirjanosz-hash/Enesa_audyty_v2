<?php

use App\Models\ActivityLog;
use App\Support\ActivityLogPresentation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->text('search_text')->nullable();
            $table->string('actor_name')->nullable();
            $table->boolean('is_noise')->default(false)->index();
        });
        $presentation = new ActivityLogPresentation;
        ActivityLog::with('user')->chunkById(250, function ($logs) use ($presentation) {
            foreach ($logs as $log) {
                // Preserve the original history; only build display/search metadata.
                DB::table('activity_logs')->where('id', $log->id)->update($presentation->indexData($log));
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['is_noise']);
            $table->dropColumn(['search_text', 'actor_name', 'is_noise']);
        });
    }
};
