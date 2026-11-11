<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('status', 100)->default('w_toku')->change();
            $table->string('archived_from_status', 100)->nullable();
        });

        Schema::create('offer_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('normalized_name', 100)->unique();
            $table->timestamps();
        });

        DB::table('offers')->where('status', 'zarchiwizowana')->select('id')
            ->addSelect(['last_status_change' => DB::table('crm_activities')->select('metadata')
                ->whereColumn('offer_id', 'offers.id')->where('type', 'offer_status_changed')
                ->orderByDesc('id')->limit(1)])
            ->chunkById(200, function ($offers): void {
                foreach ($offers as $offer) {
                    $change = json_decode($offer->last_status_change ?? 'null', true);
                    if (($change['to'] ?? null) === 'zarchiwizowana'
                        && in_array($change['from'] ?? null, ['wygrana', 'przegrana', 'anulowana'], true)) {
                        DB::table('offers')->where('id', $offer->id)->update(['archived_from_status' => $change['from']]);
                    }
                }
            });
    }

    public function down(): void
    {
        $statuses = ['w_toku', 'w_negocjacji', 'wygrana', 'przegrana', 'zarchiwizowana'];
        if (DB::table('offers')->whereNotIn('status', $statuses)->exists()) {
            throw new RuntimeException('Cannot restore the old offer status column while new statuses are in use.');
        }

        Schema::table('offers', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('w_toku')->change();
            $table->dropColumn('archived_from_status');
        });
        Schema::dropIfExists('offer_statuses');
    }
};
