<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::findOrCreate('superadmin', 'web');
        $password = (string) config('superadmin.password');
        $user = User::query()->where('email', (string) config('superadmin.email'))->first();

        if (! $user && $password === '') {
            $this->command?->warn('Pominięto tworzenie superadmina: ustaw SUPERADMIN_PASSWORD.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => (string) config('superadmin.email')],
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
}
