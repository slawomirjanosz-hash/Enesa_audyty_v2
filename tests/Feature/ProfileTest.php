<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertNotNull($user->email_verified_at);
});

test('profile avatar can be uploaded displayed and removed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();
    expect($user->avatar_data)->not->toBeNull()
        ->and($user->avatar_mime)->toBe('image/png');

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSee('data:image/png;base64,', false);

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => '1',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->avatar_data)->toBeNull()
        ->and($user->avatar_mime)->toBeNull();
});

test('profile signature can be uploaded displayed and removed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'signature' => UploadedFile::fake()->image('signature.png', 400, 120),
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');

    $user->refresh();
    expect($user->signature_data)->not->toBeNull()
        ->and($user->signature_mime)->toBe('image/png');

    $this->actingAs($user->fresh())->get('/profile')
        ->assertOk()
        ->assertSee('data:image/png;base64,', false);

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'remove_signature' => '1',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->signature_data)->toBeNull()
        ->and($user->signature_mime)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user cannot delete their account through profile', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response->assertForbidden();
    $this->assertAuthenticatedAs($user);
    $this->assertNotNull(User::find($user->id));
});

test('profile deletion endpoint remains forbidden with wrong password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response->assertForbidden();

    $this->assertNotNull($user->fresh());
});

test('every account role can edit its own profile without changing security fields', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role));
    $other = User::factory()->create();
    $originalPassword = $user->password;
    $this->actingAs($user)->get('/profile')->assertOk()
        ->assertSee('Mój profil')->assertSee('Podpis do protokołów i dokumentów HR')
        ->assertSee('Zmiana hasła')->assertDontSee('Delete Account');
    $this->patch('/profile', [
        'name' => 'Zmienione dane', 'email' => $user->email,
        'id' => $other->id, 'user_id' => $other->id,
        'role' => 'superadmin', 'roles' => ['superadmin'], 'permissions' => ['users.manage'],
        'company_id' => 100, 'companies' => [100], 'is_active' => false,
        'password' => 'injected-password', 'phone' => '111222333',
        'two_factor_secret' => 'forged', 'two_factor_confirmed_at' => now()->toDateTimeString(),
        'session_version' => 999, 'document_limit_bytes' => 999999999,
        'signature_data' => 'forged', 'has_employment_contract' => true,
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');
    $user->refresh();
    expect($user->name)->toBe('Zmienione dane')
        ->and($user->getRoleNames()->all())->toBe([$role])
        ->and($user->permissions()->count())->toBe(0)
        ->and($user->companies()->count())->toBe(0)
        ->and($user->is_active)->toBeTrue()
        ->and($user->password)->toBe($originalPassword)
        ->and($user->phone)->not->toBe('111222333')
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and((int) $user->session_version)->toBe(0)
        ->and($user->signature_data)->toBeNull()
        ->and($user->document_limit_bytes)->not->toBe(999999999)
        ->and($other->fresh()->name)->toBe($other->name);
})->with(['superadmin', 'admin', 'auditor_senior', 'auditor', 'client_admin', 'client_user']);

test('login address cannot be changed through own profile', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'other@example.com'])
        ->assertSessionHasErrors('email');
    expect($user->fresh()->email)->toBe($user->email);
});

test('signature rejects non image files and oversize uploads', function () {
    $user = User::factory()->create();
    foreach ([UploadedFile::fake()->create('signature.svg', 5, 'image/svg+xml'), UploadedFile::fake()->image('signature.png')->size(2049)] as $file) {
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'signature' => $file])
            ->assertSessionHasErrors('signature');
    }
    expect($user->fresh()->signature_data)->toBeNull();
});

test('profile and password changes cannot bypass superadmin two factor challenge', function () {
    config(['security.totp.enabled' => true]);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin'));
    $this->actingAs($user)->get('/profile')->assertRedirect(route('auth.totp'));
    $this->patch('/profile', ['name' => 'Injected', 'email' => $user->email])->assertRedirect(route('auth.totp'));
    $this->put('/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertRedirect(route('auth.totp'));
    expect($user->fresh()->name)->toBe($user->name)
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
