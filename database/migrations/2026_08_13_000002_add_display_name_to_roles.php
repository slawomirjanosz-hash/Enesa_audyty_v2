<?php

use App\Support\RolePermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('display_name', 60)->nullable()->after('name');
        });

        foreach (RolePermissionCatalog::SYSTEM_ROLES as $name) {
            DB::table('roles')->where('name', $name)->update([
                'display_name' => RolePermissionCatalog::roleLabel($name),
            ]);
        }

        DB::table('roles')->whereNull('display_name')->update(['display_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });
    }
};
