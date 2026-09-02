<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

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
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
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

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertSoftDeleted($user);
    $this->assertNull(User::find($user->id));
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
