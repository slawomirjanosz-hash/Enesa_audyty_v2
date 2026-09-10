<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_version_counters', function (Blueprint $table) {
            $table->string('series_key', 64)->primary();
            $table->unsignedBigInteger('last_version');
        });
        DB::table('iso_section_documents')->select(['id', 'audit_id', 'section_id', 'mime_type', 'title', 'version_number'])->whereNotNull('audit_id')->orderBy('id')->chunkById(200, function ($documents) {
            foreach ($documents as $document) {
                foreach (['mime_type', 'title'] as $field) {
                    $key = hash('sha256', json_encode([(int) $document->audit_id, $document->section_id, $field, $document->{$field}]));
                    $query = DB::table('document_version_counters')->where('series_key', $key);
                    $previous = (int) $query->value('last_version');
                    DB::table('document_version_counters')->updateOrInsert(['series_key' => $key], ['last_version' => max($previous, (int) $document->version_number)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_version_counters');
    }
};
