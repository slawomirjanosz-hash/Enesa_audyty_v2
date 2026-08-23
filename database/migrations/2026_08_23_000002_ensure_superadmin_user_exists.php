<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::findOrCreate('superadmin', 'web');
        $email = (string) config('superadmin.email');
        $password = (string) config('superadmin.password');

        $user = User::query()->where('email', $email)->first();

        // Never create a privileged account with a predictable password.
        // New Railway projects should provide SUPERADMIN_PASSWORD as a secret variable.
        if (! $user && $password === '') {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('superadmin.name'),
                'password' => Hash::make($password),
                'is_active' => true,
            ]
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }

    public function down(): void
    {
        // Konto może być już używane, dlatego rollback go nie usuwa.
    }
};
