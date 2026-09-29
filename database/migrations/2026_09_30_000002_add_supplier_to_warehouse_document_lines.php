<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_document_lines', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('supplier_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('supplier_name');
        });
    }
};
