<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

test('password can be updated', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role));

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    expect((int) $user->session_version)->toBe(1);
    $response->assertSessionHas('security_version', 1);
    $this->get('/profile')->assertOk();
    $this->withSession(['security_version' => 0])->get('/profile')->assertRedirect('/login');
})->with(['superadmin', 'admin', 'auditor', 'client_admin', 'client_user']);

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});
