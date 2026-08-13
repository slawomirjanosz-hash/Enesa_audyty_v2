<?php

use App\Models\AuditorCompanyAccess;
use App\Models\AuditorDocumentAccess;
use App\Models\Company;
use App\Models\Document;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['admin', 'auditor', 'client_user'] as $role) {
        Role::findOrCreate($role);
    }

    Storage::fake('local');
});

function auditorDocument(Company $company, string $name): Document
{
    $document = Document::create([
        'company_id' => $company->id,
        'type' => 'upload',
        'original_filename' => $name,
        'stored_path' => 'documents/'.$company->id.'/'.$name,
        'mime_type' => 'application/pdf',
        'size' => 4,
    ]);

    Storage::disk('local')->put($document->stored_path, 'test');

    return $document;
}

test('auditor without assignment sees no companies or documents', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $company = Company::create(['name' => 'Niewidoczna firma']);
    auditorDocument($company, 'private.pdf');

    $this->actingAs($auditor)->get('/documents')->assertOk()->assertDontSee('private.pdf');
    $this->actingAs($auditor)->get('/crm')->assertOk()->assertDontSee('Niewidoczna firma');
});

test('auditor with company document permission can download only that company document', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $allowedCompany = Company::create(['name' => 'Dozwolona firma']);
    $otherCompany = Company::create(['name' => 'Cudza firma']);
    $allowedDocument = auditorDocument($allowedCompany, 'allowed.pdf');
    $otherDocument = auditorDocument($otherCompany, 'other.pdf');

    AuditorCompanyAccess::create([
        'auditor_id' => $auditor->id,
        'company_id' => $allowedCompany->id,
        'can_view_documents' => true,
    ]);

    $this->actingAs($auditor)->get(route('documents.download', $allowedDocument))->assertOk();
    $this->actingAs($auditor)->get(route('documents.download', $otherDocument))->assertForbidden();
});

test('auditor with individual document assignment can download only that document', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $company = Company::create(['name' => 'Firma dokumentów']);
    $assignedDocument = auditorDocument($company, 'assigned.pdf');
    $otherDocument = auditorDocument($company, 'other.pdf');
    AuditorDocumentAccess::create(['user_id' => $auditor->id, 'document_id' => $assignedDocument->id]);

    $this->actingAs($auditor)->get(route('documents.download', $assignedDocument))->assertOk();
    $this->actingAs($auditor)->get(route('documents.download', $otherDocument))->assertForbidden();
});

test('auditor without price permission cannot obtain offer prices PDF or Word', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $company = Company::create(['name' => 'Firma ofertowa']);
    $offer = Offer::create([
        'company_id' => $company->id,
        'offer_number' => 'OF_TEST_001',
        'offer_full_number' => 'OF_TEST_001',
        'status' => 'w_toku',
        'kwota_netto' => 1234,
    ]);

    AuditorCompanyAccess::create([
        'auditor_id' => $auditor->id,
        'company_id' => $company->id,
        'can_view_offers' => true,
    ]);

    $this->actingAs($auditor)->get(route('offers.pdf', $offer))->assertForbidden();
    $this->actingAs($auditor)->get(route('offers.download-word', $offer))->assertForbidden();
});

test('auditor with offer access can view the offer without a server error', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $company = Company::create(['name' => 'Firma podglądu oferty']);
    $offer = Offer::create([
        'company_id' => $company->id,
        'offer_number' => 'OF_VIEW_001',
        'offer_full_number' => 'OF_VIEW_001',
        'status' => 'w_toku',
    ]);

    AuditorCompanyAccess::create([
        'auditor_id' => $auditor->id,
        'company_id' => $company->id,
        'can_view_offers' => true,
    ]);

    $this->actingAs($auditor)->get(route('offers.show', $offer))->assertOk();
});

test('auditor offer statistics include only assigned company offers', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $allowedCompany = Company::create(['name' => 'Firma dozwolona']);
    $otherCompany = Company::create(['name' => 'Firma niedozwolona']);

    foreach ([[$allowedCompany, 'OF_STATS_001'], [$otherCompany, 'OF_STATS_002'], [$otherCompany, 'OF_STATS_003']] as [$company, $number]) {
        Offer::create([
            'company_id' => $company->id,
            'offer_number' => $number,
            'offer_full_number' => $number,
            'status' => 'w_toku',
        ]);
    }

    AuditorCompanyAccess::create([
        'auditor_id' => $auditor->id,
        'company_id' => $allowedCompany->id,
        'can_view_offers' => true,
    ]);

    $this->actingAs($auditor)->get('/offers')
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats) => $stats['w_toku'] === 1);
});

test('auditor does not receive company users even with company access', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $company = Company::create(['name' => 'Firma bez użytkowników dla audytora']);
    $client = User::factory()->create();
    $client->assignRole('client_user');
    $company->users()->attach($client, ['is_admin' => false]);

    AuditorCompanyAccess::create([
        'auditor_id' => $auditor->id,
        'company_id' => $company->id,
        'can_view_offers' => true,
    ]);

    $this->actingAs($auditor)->get(route('companies.show', $company))
        ->assertOk()
        ->assertViewHas('company', fn (Company $shownCompany) => $shownCompany->relationLoaded('users') && $shownCompany->users->isEmpty());
});

test('auditor with offer price permission receives prices for assigned offer', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $company = Company::create(['name' => 'Firma z cenami']);
    $offer = Offer::create([
        'company_id' => $company->id,
        'offer_number' => 'OF_PRICE_001',
        'offer_full_number' => 'OF_PRICE_001',
        'status' => 'w_toku',
        'kwota_netto' => 1234,
    ]);

    AuditorCompanyAccess::create([
        'auditor_id' => $auditor->id,
        'company_id' => $company->id,
        'can_view_offers' => true,
        'can_view_offer_prices' => true,
    ]);

    $this->actingAs($auditor)->get(route('offers.show', $offer))
        ->assertOk()
        ->assertViewHas('offer', fn (Offer $shownOffer) => (float) $shownOffer->kwota_netto === 1234.0);
});

test('admin retains full document access and client user remains blocked from internal panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $client = User::factory()->create();
    $client->assignRole('client_user');
    $document = auditorDocument(Company::create(['name' => 'Firma administratora']), 'admin.pdf');

    $this->actingAs($admin)->get(route('documents.download', $document))->assertOk();
    $this->actingAs($client)->get('/documents')->assertForbidden();
});

test('admin can open an auditor access page while an auditor receives forbidden', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');

    $this->actingAs($admin)->get(route('settings.users.auditor-access', $auditor))->assertOk();
    $this->actingAs($auditor)->get(route('settings.users.auditor-access', $auditor))->assertForbidden();
});

test('admin can add two different company accesses for an auditor', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $firstCompany = Company::create(['name' => 'Pierwsza firma']);
    $secondCompany = Company::create(['name' => 'Druga firma']);

    $this->actingAs($admin)->post(route('settings.users.auditor-access.store', $auditor), [
        'company_id' => $firstCompany->id,
        'can_view_dashboard' => true,
    ])->assertRedirect();
    $this->actingAs($admin)->post(route('settings.users.auditor-access.store', $auditor), [
        'company_id' => $secondCompany->id,
        'can_view_documents' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('auditor_company_accesses', ['auditor_id' => $auditor->id, 'company_id' => $firstCompany->id, 'can_view_dashboard' => true]);
    $this->assertDatabaseHas('auditor_company_accesses', ['auditor_id' => $auditor->id, 'company_id' => $secondCompany->id, 'can_view_documents' => true]);
});

test('admin can update access flags and remove an auditor company access', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $access = AuditorCompanyAccess::create([
        'auditor_id' => $auditor->id,
        'company_id' => Company::create(['name' => 'Firma do edycji'])->id,
        'can_view_dashboard' => true,
    ]);

    $this->actingAs($admin)->patch(route('settings.users.auditor-access.update', [$auditor, $access]), [
        'can_view_offers' => true,
        'can_view_offer_prices' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('auditor_company_accesses', [
        'id' => $access->id,
        'can_view_dashboard' => false,
        'can_view_offers' => true,
        'can_view_offer_prices' => true,
    ]);

    $this->actingAs($admin)->delete(route('settings.users.auditor-access.destroy', [$auditor, $access]))->assertRedirect();
    $this->assertDatabaseMissing('auditor_company_accesses', ['id' => $access->id]);
});

test('an auditor company access cannot be duplicated', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $company = Company::create(['name' => 'Firma bez duplikatu']);
    AuditorCompanyAccess::create(['auditor_id' => $auditor->id, 'company_id' => $company->id]);

    $this->actingAs($admin)->post(route('settings.users.auditor-access.store', $auditor), [
        'company_id' => $company->id,
        'can_view_documents' => true,
    ])->assertRedirect();

    $this->assertSame(1, AuditorCompanyAccess::where('auditor_id', $auditor->id)->where('company_id', $company->id)->count());
});

test('auditor is forbidden from all user settings endpoints including direct writes', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $targetUser = User::factory()->create();
    $targetAuditor = User::factory()->create();
    $targetAuditor->assignRole('auditor');

    $this->actingAs($auditor)->get(route('settings.users.index'))->assertForbidden();
    $this->actingAs($auditor)->post(route('settings.users.store'), [])->assertForbidden();
    $this->actingAs($auditor)->patch(route('settings.users.update', $targetUser), [])->assertForbidden();
    $this->actingAs($auditor)->post(route('settings.users.auditor-access.store', $targetAuditor), [])->assertForbidden();
});
