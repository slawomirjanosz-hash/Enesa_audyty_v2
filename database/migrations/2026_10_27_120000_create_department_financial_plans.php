<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';
        if ($mysql && (int) DB::selectOne('SELECT GET_LOCK(?, 60) AS acquired', ['department_financial_plans_v1'])->acquired !== 1) {
            throw new RuntimeException('Nie udało się uzyskać blokady instalacji planów finansowych.');
        }
        try {
            $this->install();
        } finally {
            if ($mysql) {
                DB::selectOne('SELECT RELEASE_LOCK(?)', ['department_financial_plans_v1']);
            }
        }
    }

    private function install(): void
    {
        $this->createIfMissing('financial_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        foreach (['financial_department_members', 'financial_department_access'] as $name) {
            $index = $name === 'financial_department_members' ? 'finance_department_member_unique' : 'finance_department_access_unique';
            $this->createIfMissing($name, function (Blueprint $table) use ($index) {
                $table->id();
                $table->foreignId('financial_department_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->unique(['financial_department_id', 'user_id'], $index);
            });
            // MySQL persists CREATE TABLE even when a later index statement fails.
            // Resume the partial installation without dropping tables or their rows.
            if (! Schema::hasIndex($name, $index)) {
                Schema::table($name, fn (Blueprint $table) => $table->unique(['financial_department_id', 'user_id'], $index));
            }
            $foreignKeys = collect(Schema::getForeignKeys($name));
            if (! $foreignKeys->contains(fn ($key) => $key['columns'] === ['financial_department_id'])) {
                Schema::table($name, fn (Blueprint $table) => $table->foreign('financial_department_id')->references('id')->on('financial_departments')->cascadeOnDelete());
            }
            if (! $foreignKeys->contains(fn ($key) => $key['columns'] === ['user_id'])) {
                Schema::table($name, fn (Blueprint $table) => $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete());
            }
        }
        $this->createIfMissing('financial_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->text('approved_snapshot')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['financial_department_id', 'year']);
        });
        $this->createIfMissing('financial_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_plan_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('name', 200);
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('qualification', 20)->default('real');
            $table->unsignedTinyInteger('probability')->default(50);
            $table->string('budget_accuracy', 20)->default('initial');
            $table->date('start_date');
            $table->date('end_date');
            // Encrypted casts keep salaries and financial assumptions out of plain database exports.
            $table->text('revenue_override')->nullable();
            $table->text('cost_override')->nullable();
            $table->text('salary_cost')->nullable();
            $table->text('car_cost')->nullable();
            $table->text('actual_salary_cost')->nullable();
            $table->text('actual_car_cost')->nullable();
            $table->text('actual_cost')->nullable();
            $table->text('risk_reserve')->nullable();
            $table->string('car_name', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        $this->createIfMissing('financial_plan_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 40);
            $table->text('payload');
            $table->timestamps();
        });
        foreach (['financial_plans.view', 'financial_plans.manage'] as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            // Financial and salary access must be granted explicitly, including on fresh installs.
            $permission->roles()->detach();
        }
        foreach (DB::table('company_settings')->get(['id', 'enabled_modules']) as $settings) {
            $modules = $settings->enabled_modules === null ? null : json_decode($settings->enabled_modules, true);
            if (is_array($modules) && in_array('projects', $modules, true)) {
                DB::table('company_settings')->where('id', $settings->id)->update([
                    'enabled_modules' => json_encode(array_values(array_unique([...$modules, 'financial_plans']))),
                ]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createIfMissing(string $name, Closure $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);
        }
    }

    public function down(): void
    {
        foreach (['financial_plan_changes', 'financial_plan_lines', 'financial_plans', 'financial_department_access', 'financial_department_members', 'financial_departments'] as $table) {
            Schema::dropIfExists($table);
        }
        Permission::whereIn('name', ['financial_plans.view', 'financial_plans.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
