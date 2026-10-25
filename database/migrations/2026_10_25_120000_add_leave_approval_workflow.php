<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('hr_leaves', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('legacy')->index();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedInteger('approval_revision')->default(1);
        });
        Schema::create('hr_leave_approval_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_leave_id')->unique()->constrained('hr_leaves')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Permission::findOrCreate('hr.leaves.accounting', 'web');
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_approval_tasks');
        Schema::table('hr_leaves', fn (Blueprint $table) => $table->dropConstrainedForeignId('approver_id'));
        Schema::table('hr_leaves', fn (Blueprint $table) => $table->dropColumn(['approval_status', 'approver_name', 'decided_at', 'decision_note', 'approval_revision']));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('supervisor_id'));
    }
};
