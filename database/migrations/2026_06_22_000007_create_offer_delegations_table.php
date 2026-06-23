<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->integer('km_do_klienta')->nullable();
            $table->integer('czas_dojazdu_min')->nullable();
            $table->integer('liczba_wyjazdow')->default(1);
            $table->boolean('czy_kilkudniowy')->default(false);
            $table->integer('liczba_noc')->default(0);
            $table->integer('liczba_osob')->default(1);
            $table->decimal('stawka_noc', 8, 2)->default(300.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_delegations');
    }
};
