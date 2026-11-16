<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('staff can save existing company notes separately from company details', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $company = Company::create([
        'name' => 'Klient', 'company_type' => 'client', 'status' => 'active', 'notes' => 'Dotychczasowe ustalenia',
    ]);
    $card = route('companies.show', $company);
    $save = route('companies.notes.update', $company);

    $this->actingAs($admin)->get($card)->assertOk()->assertSee('Dotychczasowe ustalenia')->assertSee('Zapisz notatki');
    $notes = "Pierwsze ustalenie\nDruga linia <b>notatki</b>";
    $this->patch($save, ['notes' => $notes, 'name' => 'Inna nazwa'])->assertRedirect($card.'#notes');
    expect($company->fresh())->notes->toBe($notes)->name->toBe('Klient');
    $this->get($card)->assertOk()->assertSee('<b>notatki</b>')->assertDontSee('<b>notatki</b>', false);

    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->companies()->attach($company);
    $this->actingAs($client)->patch($save, ['notes' => 'Nowa treść'])->assertForbidden();
    expect($company->fresh()->notes)->toBe($notes);

    $this->actingAs($admin)->patch($save, ['notes' => ''])->assertRedirect($card.'#notes');
    expect($company->fresh()->notes)->toBeNull();
});
