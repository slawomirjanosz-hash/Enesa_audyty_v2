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
        'stored_path' => 'documents/' . $company->id . '/' . $name,
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