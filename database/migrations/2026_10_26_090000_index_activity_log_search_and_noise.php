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
        // MySQL DDL survives a failed migration; resume without dropping history.
        if (! Schema::hasColumn('activity_logs', 'search_text')) {
            Schema::table('activity_logs', fn (Blueprint $table) => $table->longText('search_text')->nullable());
        } elseif (DB::getDriverName() === 'mysql' && Schema::getColumnType('activity_logs', 'search_text') !== 'longtext') {
            Schema::table('activity_logs', fn (Blueprint $table) => $table->longText('search_text')->nullable()->change());
        }
        if (! Schema::hasColumn('activity_logs', 'actor_name')) {
            Schema::table('activity_logs', fn (Blueprint $table) => $table->string('actor_name')->nullable());
        }
        if (! Schema::hasColumn('activity_logs', 'is_noise')) {
            Schema::table('activity_logs', fn (Blueprint $table) => $table->boolean('is_noise')->default(false));
        }
        if (! Schema::hasIndex('activity_logs', 'activity_logs_is_noise_index')) {
            Schema::table('activity_logs', fn (Blueprint $table) => $table->index('is_noise'));
        }
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
