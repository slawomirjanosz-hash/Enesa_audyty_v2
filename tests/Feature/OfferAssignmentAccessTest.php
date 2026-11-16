<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\Offer;
use App\Models\OfferRequest;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('staff offer access follows assignment on lists and direct routes', function () {
    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('employee'));
    foreach (['crm.view', 'documents.view', 'offers.view', 'offers.edit', 'offers.delete', 'offers.prices.view'] as $permission) {
        $employee->givePermissionTo(Permission::findOrCreate($permission));
    }
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $company = Company::create(['name' => 'Klient', 'company_type' => 'client', 'status' => 'active']);
    $assignedOffer = Offer::create(['company_id' => $company->id, 'offer_number' => 'O-1', 'offer_title' => 'OFERTA PRZYDZIELONA', 'assigned_user_id' => $employee->id]);
    $otherOffer = Offer::create(['company_id' => $company->id, 'offer_number' => 'O-2', 'offer_title' => 'OFERTA CUDZA', 'assigned_user_id' => $admin->id, 'created_by_id' => $employee->id, 'status' => 'zarchiwizowana']);
    $assignedRequest = OfferRequest::create(['company_id' => $company->id, 'title' => 'ZAPYTANIE PRZYDZIELONE', 'created_by_id' => $employee->id]);
    $otherRequest = OfferRequest::create(['company_id' => $company->id, 'title' => 'ZAPYTANIE CUDZE', 'created_by_id' => $admin->id]);
    $otherDocument = Document::create(['company_id' => $company->id, 'offer_id' => $otherOffer->id, 'type' => 'offer_pdf',
        'original_filename' => 'cudza-oferta.pdf', 'stored_path' => 'cudza-oferta.pdf', 'size' => 100]);

    $this->actingAs($employee)->get(route('offers.index'))->assertOk()
        ->assertSee('OFERTA PRZYDZIELONA')->assertDontSee('OFERTA CUDZA');
    $this->get(route('companies.show', $company))->assertOk()
        ->assertSee('OFERTA PRZYDZIELONA')->assertSee('ZAPYTANIE PRZYDZIELONE')
        ->assertDontSee('OFERTA CUDZA')->assertDontSee('ZAPYTANIE CUDZE')->assertDontSee('cudza-oferta.pdf');
    $this->get(route('documents.index'))->assertOk()->assertDontSee('cudza-oferta.pdf');
    $this->get(route('documents.download', $otherDocument))->assertForbidden();
    $this->get(route('offers.show', $assignedOffer))->assertOk();
    $this->get(route('offer-requests.show', $assignedRequest))->assertOk();
    $this->get(route('offers.show', $otherOffer))->assertForbidden();
    $this->get(route('offers.edit', $otherOffer))->assertForbidden();
    $this->get(route('offers.pdf', $otherOffer))->assertForbidden();
    $this->delete(route('offers.destroy', $otherOffer))->assertForbidden();
    $this->get(route('offer-requests.show', $otherRequest))->assertForbidden();
    $this->get(route('offer-requests.pdf', $otherRequest))->assertForbidden();
    $this->patch(route('offer-requests.update-status', $otherRequest), ['status' => 'w_toku'])->assertForbidden();
    $this->put(route('offer-requests.update', $assignedRequest), [
        'title' => $assignedRequest->title, 'assigned_user_id' => $admin->id,
    ])->assertSessionHasNoErrors();
    expect($assignedRequest->fresh()->assigned_user_id)->toBeNull();

    $this->actingAs($admin)->get(route('offers.show', $otherOffer))->assertOk();
    $this->put(route('offer-requests.update', $otherRequest), [
        'title' => $otherRequest->title, 'assigned_user_id' => $employee->id,
    ])->assertSessionHasNoErrors();
    $this->actingAs($employee)->get(route('offer-requests.show', $otherRequest))->assertOk();
});

test('an offer can be shared with multiple staff and later revoked', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $staff = User::factory()->count(2)->create();
    $role = Role::findOrCreate('employee');
    $role->givePermissionTo(Permission::findOrCreate('offers.view'));
    $staff->each(fn (User $user) => $user->assignRole($role));
    $company = Company::create(['name' => 'Klient', 'company_type' => 'client']);
    $data = [
        'company_id' => $company->id, 'offer_number' => 'O-SHARED', 'status' => 'w_toku',
        'assigned_user_id' => $admin->id, 'liczba_wyjazdow' => 1, 'liczba_noc' => 0, 'liczba_osob' => 1, 'stawka_noc' => 0,
    ];

    $this->actingAs($admin)->get(route('offers.create'))->assertOk()->assertSee('Kto może widzieć ofertę');
    $this->post(route('offers.store'), $data + ['member_ids' => $staff->modelKeys()])->assertSessionHasNoErrors();
    $offer = Offer::where('offer_number', 'O-SHARED')->sole();

    foreach ($staff as $member) {
        $this->actingAs($member)->get(route('offers.index'))->assertOk()->assertSee('O-SHARED');
        $this->get(route('offers.show', $offer))->assertOk();
        $this->get(route('offers.edit', $offer))->assertForbidden();
    }

    $this->actingAs($admin)->get(route('offers.edit', $offer))->assertOk()->assertSee('Kto może widzieć ofertę');
    $this->put(route('offers.update', $offer), $data)->assertSessionHasNoErrors();
    expect($offer->members()->count())->toBe(2);
    $this->put(route('offers.update', $offer), $data + ['offer_members_present' => '1'])->assertSessionHasNoErrors();
    expect($offer->members()->count())->toBe(0);
    $this->actingAs($staff->first())->get(route('offers.show', $offer))->assertForbidden();
});
