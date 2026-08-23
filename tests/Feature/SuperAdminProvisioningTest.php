<?php

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

test('superadmin is provisioned from environment configuration without overwriting an existing password', function () {
    config()->set('superadmin.email', 'proximalumine@gmail.com');
    config()->set('superadmin.name', 'Super Admin');
    config()->set('superadmin.password', 'Pierwsze-bezpieczne-haslo!');

    $this->seed(SuperAdminSeeder::class);

    $user = User::where('email', 'proximalumine@gmail.com')->firstOrFail();
    expect($user->hasRole('superadmin'))->toBeTrue()
        ->and(Hash::check('Pierwsze-bezpieczne-haslo!', $user->password))->toBeTrue();

    config()->set('superadmin.password', 'Drugie-haslo-ktore-nie-moze-nadpisac!');
    $this->seed(SuperAdminSeeder::class);

    expect(Hash::check('Pierwsze-bezpieczne-haslo!', $user->fresh()->password))->toBeTrue();
});

test('superadmin is not created without a configured secret password', function () {
    config()->set('superadmin.email', 'nowy-superadmin@example.test');
    config()->set('superadmin.password', null);

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('email', 'nowy-superadmin@example.test')->exists())->toBeFalse();
});
