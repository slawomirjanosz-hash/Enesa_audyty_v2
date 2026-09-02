<?php

use App\Mail\ClientAccepted;
use App\Mail\NewClientUser;
use App\Mail\TaskAssigned;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['superadmin', 'admin', 'client_admin', 'client_user'] as $role) {
        Role::findOrCreate($role);
    }
    Mail::fake();
});

test('accepting a client sends mail only after explicit confirmation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $firstCompany = Company::create(['name' => 'Klient bez maila', 'status' => 'pending']);
    $firstClient = User::factory()->create();
    $firstClient->assignRole('client_admin');
    $firstCompany->users()->attach($firstClient, ['is_admin' => true]);

    $this->actingAs($admin)->post(route('companies.accept', $firstCompany))
        ->assertSessionHas('success');

    Mail::assertNothingSent();
    expect($firstCompany->refresh()->status)->toBe('active');

    $secondCompany = Company::create(['name' => 'Klient z mailem', 'status' => 'pending']);
    $secondClient = User::factory()->create();
    $secondClient->assignRole('client_admin');
    $secondCompany->users()->attach($secondClient, ['is_admin' => true]);

    $this->actingAs($admin)->post(route('companies.accept', $secondCompany), ['send_email' => '1'])
        ->assertSessionHas('success');

    Mail::assertSent(ClientAccepted::class, fn (ClientAccepted $mail) => $mail->hasTo($secondClient->email));
});

test('staff creates client accounts without mail unless the choice is checked', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Firma testowa', 'status' => 'active']);

    $this->actingAs($admin)->post(route('companies.users.store', $company), [
        'first_name' => 'Jan', 'last_name' => 'Bezmailowy', 'email' => 'jan-no-mail@example.test',
        'role' => 'client_user', 'password' => 'Bezpieczne123!',
    ])->assertSessionHas('success');

    Mail::assertNotSent(NewClientUser::class);

    $this->actingAs($admin)->post(route('companies.users.store', $company), [
        'first_name' => 'Anna', 'last_name' => 'Mailowa', 'email' => 'anna-mail@example.test',
        'role' => 'client_user', 'password' => 'Bezpieczne123!', 'send_email' => '1',
    ])->assertSessionHas('success');

    Mail::assertSent(NewClientUser::class, fn (NewClientUser $mail) => $mail->hasTo('anna-mail@example.test'));
});

test('client administrator sees temporary password when email is not sent', function () {
    $clientAdmin = User::factory()->create();
    $clientAdmin->assignRole('client_admin');
    $company = Company::create(['name' => 'Firma klienta', 'status' => 'active']);
    $company->users()->attach($clientAdmin, ['is_admin' => true]);

    $this->actingAs($clientAdmin)->post(route('client.users.store'), [
        'first_name' => 'Piotr', 'last_name' => 'Nowy', 'email' => 'piotr-client@example.test',
        'is_admin' => '0',
    ])->assertSessionHas('success')->assertSessionHas('temporary_password');

    Mail::assertNotSent(NewClientUser::class);

    $this->actingAs($clientAdmin)->post(route('client.users.store'), [
        'first_name' => 'Maria', 'last_name' => 'Nowa', 'email' => 'maria-client@example.test',
        'is_admin' => '0', 'send_email' => '1',
    ])->assertSessionHas('success')->assertSessionMissing('temporary_password');

    Mail::assertSent(NewClientUser::class, fn (NewClientUser $mail) => $mail->hasTo('maria-client@example.test'));
});

test('client administrator cannot attach an existing account or change its role', function () {
    $clientAdmin = User::factory()->create();
    $clientAdmin->assignRole('client_admin');
    $company = Company::create(['name' => 'Firma klienta', 'status' => 'active']);
    $company->users()->attach($clientAdmin, ['is_admin' => true]);

    $superadmin = User::factory()->create(['email' => 'owner@example.test']);
    $superadmin->assignRole('superadmin');

    $this->actingAs($clientAdmin)->from(route('client.users'))->post(route('client.users.store'), [
        'first_name' => 'Próba',
        'last_name' => 'Przejęcia',
        'email' => $superadmin->email,
        'is_admin' => '1',
    ])->assertRedirect(route('client.users'))->assertSessionHasErrors('email');

    expect($superadmin->refresh()->hasRole('superadmin'))->toBeTrue()
        ->and($superadmin->hasAnyRole(['client_admin', 'client_user']))->toBeFalse()
        ->and($company->users()->whereKey($superadmin->id)->exists())->toBeFalse();
});

test('crm task assignment mail remains automatic', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $assignedUser = User::factory()->create();
    $assignedUser->assignRole('admin');

    $this->actingAs($admin)->post(route('crm.tasks.store'), [
        'title' => 'Skontaktować się z klientem',
        'assigned_to' => $assignedUser->id,
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => now()->addDay()->format('Y-m-d'),
    ])->assertSessionHas('success');

    Mail::assertSent(TaskAssigned::class, fn (TaskAssigned $mail) => $mail->hasTo($assignedUser->email));
});
