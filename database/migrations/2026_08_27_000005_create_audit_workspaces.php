<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->string('number')->nullable()->unique()->after('company_id');
            $table->foreignId('manager_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable()->after('manager_id');
            $table->date('end_date')->nullable()->after('start_date');
            $table->decimal('contract_value', 15, 2)->default(0)->after('end_date');
            $table->text('description')->nullable()->after('contract_value');
            $table->foreignId('created_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
        });
        Schema::create('audit_user', function (Blueprint $table) {
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['audit_id', 'user_id']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('audit_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
        Schema::create('audit_financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cost', 'invoice']);
            $table->string('name');
            $table->string('document_number')->nullable();
            $table->date('entry_date');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['planned', 'issued', 'paid'])->default('planned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('audit_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('status', ['draft', 'ready', 'completed'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('energy_passports', function (Blueprint $table) {
            $table->foreignId('audit_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('energy_passports', fn (Blueprint $table) => $table->dropConstrainedForeignId('audit_id'));
        Schema::dropIfExists('audit_surveys');
        Schema::dropIfExists('audit_financial_entries');
        Schema::table('tasks', fn (Blueprint $table) => $table->dropConstrainedForeignId('audit_id'));
        Schema::dropIfExists('audit_user');
        Schema::table('audits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['number']);
            $table->dropColumn(['number', 'start_date', 'end_date', 'contract_value', 'description']);
        });
    }
};
