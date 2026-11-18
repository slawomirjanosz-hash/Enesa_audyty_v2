<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Document;
use App\Models\Offer;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditorAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function boardReader(): User
{
    $user = User::factory()->create(['individual_permissions' => true]);
    $user->assignRole(Role::findOrCreate('supervisory_board'));
    $user->givePermissionTo(Permission::findOrCreate('system.full_read_only'));
    CompanySettings::create(['name' => 'Firma', 'enabled_modules' => array_keys(CompanySettings::APP_MODULES)]);

    return $user;
}

test('full reader sees unassigned projects offers documents and settings', function () {
    Storage::fake('local');
    $reader = boardReader();
    $company = Company::create(['name' => 'Firma klienta', 'company_type' => 'client']);
    $project = Project::create(['company_id' => $company->id, 'number' => 'READ-PROJECT', 'name' => 'Projekt rady', 'status' => 'active']);
    $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'READ-OFFER', 'offer_title' => 'Oferta rady', 'status' => 'w_toku']);
    $document = Document::create(['company_id' => $company->id, 'project_id' => $project->id, 'type' => 'upload',
        'original_filename' => 'rada.txt', 'stored_path' => 'rada.txt', 'size' => 4]);
    Storage::disk('local')->put('rada.txt', 'test');

    $this->actingAs($reader)->get(route('projects.index'))->assertOk()->assertSee('READ-PROJECT');
    $this->get(route('projects.show', $project))->assertOk();
    $this->get(route('offers.index'))->assertOk()->assertSee('READ-OFFER');
    $this->get(route('offers.show', $offer))->assertOk();
    $this->get(route('companies.show', $company))->assertOk()->assertSee('READ-OFFER');
    $this->get(route('documents.index'))->assertOk()->assertSee('rada.txt');
    $this->get(route('documents.download', $document))->assertDownload('rada.txt');
    foreach (['settings.users.index', 'settings.roles.index', 'settings.company', 'settings.archive.index', 'activity-log.index', 'hr.index', 'financial-plans.index', 'expenses.index', 'warehouse.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->post(route('client-zone.impersonate', $company))->assertRedirect(route('client-zone.dashboard'));
    $this->get(route('client-zone.dashboard'))->assertOk();
    $this->post(route('client-zone.stop'))->assertRedirect(route('client-zone.index'));
    expect(app(AuditorAccessService::class)->hasFullAccess($reader))->toBeFalse()
        ->and($reader->can('update', $project))->toBeFalse()
        ->and($reader->can('viewPrices', $offer))->toBeTrue();
});

test('read only overrides write permissions and blocks direct writes before controller execution', function () {
    $reader = boardReader();
    $reader->givePermissionTo(Permission::findOrCreate('system.full_access'));
    $reader->givePermissionTo(Permission::findOrCreate('settings.users.manage'));
    $company = Company::create(['name' => 'Pierwotna nazwa', 'company_type' => 'client']);
    $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'READ-ONLY', 'status' => 'w_toku']);
    $this->actingAs($reader);
    foreach ([
        ['PUT', route('companies.update', $company), ['name' => 'Zmieniona nazwa']],
        ['PATCH', route('offers.status', $offer), ['status' => 'zarchiwizowana']],
        ['DELETE', route('offers.destroy', $offer), []],
        ['POST', route('settings.company.test-mail'), []],
        ['POST', route('settings.roles.store'), []],
        ['POST', route('offers.store'), []],
    ] as [$method, $url, $data]) {
        $this->json($method, $url, $data)->assertForbidden();
    }
    expect($company->fresh()->name)->toBe('Pierwotna nazwa')
        ->and($offer->fresh()->status)->toBe('w_toku')
        ->and($reader->can('settings.users.manage'))->toBeFalse()
        ->and($reader->can('delete', $offer))->toBeFalse();
    $this->post('/logout')->assertRedirect();
    $this->assertGuest();
});

test('read screens cannot persist incidental database changes', function () {
    $reader = boardReader();
    Route::middleware('web')->get('/readonly-test', function () {
        DB::table('companies')->insert(['name' => 'Accidental write']);

        return 'Preview';
    });
    $this->actingAs($reader)->get('/readonly-test')->assertOk();
    $this->assertDatabaseMissing('companies', ['name' => 'Accidental write']);
});
