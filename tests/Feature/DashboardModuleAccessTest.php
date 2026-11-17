<?php

use App\Models\AuditorCompanyAccess;
use App\Models\Company;
use App\Models\Document;
use App\Models\Offer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('company and dashboard honour module permissions and documents honour project access', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('auditor'));
    $user->forceFill(['individual_permissions' => true])->save();
    foreach (['dashboard.view', 'crm.view', 'documents.view', 'dashboard.documents.view', 'projects.documents.view'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission));
    }

    $company = Company::create(['name' => 'Klient dashboardu', 'company_type' => 'client', 'status' => 'active', 'show_in_dashboard' => true]);
    AuditorCompanyAccess::create([
        'auditor_id' => $user->id, 'company_id' => $company->id,
        'can_view_dashboard' => true, 'can_view_offers' => true, 'can_view_offer_prices' => true, 'can_view_documents' => true,
    ]);
    $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'OF-PRIVATE-123', 'status' => 'wygrana', 'kwota_netto' => 123456]);
    $assigned = Project::create(['company_id' => $company->id, 'manager_id' => $user->id, 'number' => 'PR-ASSIGNED-123', 'name' => 'Projekt przypisany', 'status' => 'active']);
    $other = Project::create(['company_id' => $company->id, 'number' => 'PR-PRIVATE-123', 'name' => 'Projekt niedostępny', 'status' => 'active']);
    $folder = $other->documentFolders()->create(['name' => 'Dokumentacja']);

    Storage::fake('local');
    $makeDocument = function (string $name, array $relations = []) use ($company): Document {
        Storage::disk('local')->put('documents/'.$name, 'test');

        return Document::create($relations + [
            'company_id' => $company->id, 'type' => 'upload', 'original_filename' => $name,
            'stored_path' => 'documents/'.$name, 'size' => 4,
        ]);
    };
    $visible = $makeDocument('public-company.pdf');
    $assignedDoc = $makeDocument('assigned-project.pdf', ['project_id' => $assigned->id]);
    $privateDoc = $makeDocument('private-project.pdf', ['project_id' => $other->id]);
    $folderDoc = $makeDocument('private-folder-project.pdf', ['project_document_folder_id' => $folder->id]);
    $offerDoc = $makeDocument('private-offer.pdf', ['offer_id' => $offer->id]);

    $this->actingAs($user);
    $this->get(route('dashboard'))->assertOk()->assertDontSee('OF-PRIVATE-123')->assertDontSee('Zaakceptowane oferty')
        ->assertViewHas('stats', fn ($stats) => $stats['active_projects'] === 0);
    $this->get(route('companies.show', $company))->assertOk()->assertDontSee('OF-PRIVATE-123')->assertDontSee('PR-ASSIGNED-123')
        ->assertDontSee('private-offer.pdf')->assertDontSee('assigned-project.pdf');
    $this->get(route('offers.show', $offer))->assertForbidden();
    $this->get(route('projects.show', $assigned))->assertForbidden();
    $this->get(route('documents.index'))->assertOk()->assertSee('public-company.pdf')->assertDontSee('assigned-project.pdf')
        ->assertDontSee('private-project.pdf')->assertDontSee('private-folder-project.pdf')->assertDontSee('private-offer.pdf');
    foreach ([$assignedDoc, $privateDoc, $folderDoc, $offerDoc] as $denied) {
        $this->get(route('documents.download', $denied))->assertForbidden();
    }
    $this->get(route('documents.download', $visible))->assertOk();

    $user->givePermissionTo(Permission::findOrCreate('projects.view'), Permission::findOrCreate('offers.view'));
    $this->get(route('companies.show', $company))->assertOk()->assertSee('OF-PRIVATE-123')
        ->assertSee('PR-ASSIGNED-123')->assertDontSee('PR-PRIVATE-123');
    $this->get(route('documents.index'))->assertOk()->assertSee('assigned-project.pdf')->assertDontSee('private-project.pdf')
        ->assertDontSee('private-folder-project.pdf');
    $this->get(route('documents.index', ['q' => 'private', 'table_sort' => 'name']))->assertOk()
        ->assertDontSee('private-project.pdf')->assertDontSee('private-folder-project.pdf');
    $this->get(route('documents.download', $assignedDoc))->assertOk();
    $this->get(route('documents.download', $privateDoc))->assertForbidden();
    $this->get(route('documents.download', $folderDoc))->assertForbidden();

    AuditorCompanyAccess::where('auditor_id', $user->id)->where('company_id', $company->id)->update(['can_view_offer_prices' => false]);
    $this->get(route('dashboard'))->assertOk()->assertSee('OF-PRIVATE-123')->assertDontSee('123 456,00');
    $this->get(route('companies.show', $company))->assertOk()->assertSee('OF-PRIVATE-123')->assertDontSee('123,456.00');
});
