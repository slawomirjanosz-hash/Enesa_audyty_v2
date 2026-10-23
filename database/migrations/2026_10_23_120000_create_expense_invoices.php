<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_unassigned')->default(false)->index();
            $table->date('purchase_date')->index();
            $table->string('invoice_number')->nullable();
            $table->string('ksef_number', 100)->nullable()->unique();
            $table->string('wz_number')->nullable();
            $table->string('supplier_name');
            $table->string('supplier_nip', 20)->nullable();
            $table->decimal('net_amount', 14, 2)->nullable();
            $table->decimal('gross_amount', 14, 2)->nullable();
            $table->string('payment_method', 20)->default('unknown');
            $table->string('payment_source', 20)->default('unknown');
            $table->string('payment_status', 20)->default('unknown');
            $table->string('paid_by')->nullable();
            $table->string('cost_center')->nullable();
            $table->text('description')->nullable();
            $table->text('accounting_note')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['owner_id', 'purchase_date']);
        });
        Schema::create('expense_scan_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->json('result')->nullable();
            $table->timestamp('created_at');
            $table->unique(['user_id', 'fingerprint']);
        });
        $use = Permission::findOrCreate('expenses.use', 'web');
        Permission::findOrCreate('expenses.accounting', 'web');
        Role::whereNotIn('name', ['client_admin', 'client_user'])->get()->each(fn ($role) => $role->givePermissionTo($use));
        User::withTrashed()->whereHas('roles')->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))
            ->each(fn ($user) => $user->givePermissionTo($use));
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_scan_reads');
        Schema::dropIfExists('expense_invoices');
    }
};
