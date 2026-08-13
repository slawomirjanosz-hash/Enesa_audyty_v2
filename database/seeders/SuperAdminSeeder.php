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
        $role = Role::firstOrCreate(['name' => 'superadmin']);

        $user = User::firstOrCreate(
            ['email' => 'proximalumine@gmail.com'],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('Gwiazda1!'),
                'is_active' => true,
            ]
        );

        $user->assignRole($role);
    }
}
