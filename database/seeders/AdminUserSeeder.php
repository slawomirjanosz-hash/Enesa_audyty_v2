<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@enesa.pl'],
            [
                'name' => 'Admin ENESA',
                'password' => Hash::make('zmien_haslo_123'),
            ]
        );

        $user->assignRole('admin');
        app(UserPermissions::class)->initialize($user);
    }
}
