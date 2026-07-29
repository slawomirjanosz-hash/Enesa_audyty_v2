<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('usługa');
            $table->decimal('net_unit_price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_catalog_items');
    }
};
