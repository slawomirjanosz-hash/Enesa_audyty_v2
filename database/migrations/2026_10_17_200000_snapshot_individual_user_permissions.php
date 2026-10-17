<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('individual_permissions')->default(false);
        });
        $type = (new User)->getMorphClass();
        DB::table('users')->orderBy('id')->chunkById(100, function ($users) use ($type): void {
            DB::transaction(function () use ($users, $type): void {
                $ids = $users->pluck('id');
                $permissions = DB::table('model_has_roles as members')
                    ->join('role_has_permissions as grants', 'grants.role_id', '=', 'members.role_id')
                    ->where('members.model_type', $type)->whereIn('members.model_id', $ids)
                    ->select('members.model_id', 'grants.permission_id')->distinct()->get();
                foreach ($permissions->chunk(200) as $chunk) {
                    DB::table('model_has_permissions')->insertOrIgnore($chunk->map(fn ($permission) => [
                        'model_type' => $type, 'model_id' => $permission->model_id, 'permission_id' => $permission->permission_id,
                    ])->all());
                }
                DB::table('users')->whereIn('id', $ids)->update(['individual_permissions' => true]);
            });
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('individual_permissions'));
    }
};
