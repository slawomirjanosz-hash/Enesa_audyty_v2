<?php

use App\Models\Audit;
use App\Models\Company;
use App\Models\Cylinder;
use App\Models\CylinderInspectionSeries;
use App\Models\Document;
use App\Models\HrLeave;
use App\Models\HrVehicle;
use App\Models\Offer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findOrCreate('admin'));
    $this->actingAs($this->admin);
});

function archiveAccount(string $role = 'client_user'): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role));
    $user->delete();

    return $user;
}

test('permanent archived account deletion frees email preserves companies and clears authentication data', function () {
    $user = archiveAccount();
    $company = Company::create(['name' => 'Aktywna firma']);
    $company->users()->attach($user->id);
    DB::table('sessions')->insert(['id' => 'old-account-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'old-reset-token']);
    $this->from(route('settings.archive.index'))->delete(route('settings.users.forceDestroy', $user), ['confirm_delete' => 1])->assertSessionHas('success');
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('sessions', ['id' => 'old-account-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseHas('companies', ['id' => $company->id]);
    $this->post(route('settings.users.store'), ['name' => 'Nowe konto', 'email' => $user->email, 'role' => 'client_user', 'password' => 'FakePassword123!'])->assertSessionHas('success')->assertSessionHasNoErrors();
    expect(User::where('email', $user->email)->firstOrFail()->id)->not->toBe($user->id);
});

test('deleting an archived employee removes only their HR and private vehicles', function () {
    $user = archiveAccount('auditor');
    $other = User::factory()->create();
    $private = HrVehicle::create(['name' => 'Prywatny', 'type' => 'private', 'created_by' => $user->id, 'user_id' => $user->id, 'registration_number' => 'PRIVATE']);
    $shared = HrVehicle::create(['name' => 'Firmowy', 'type' => 'company', 'created_by' => $user->id, 'registration_number' => 'SHARED']);
    HrLeave::create(['user_id' => $user->id, 'type' => 'annual', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'days' => 2]);
    $this->delete(route('settings.users.forceDestroy', $user), ['confirm_delete' => 1])->assertSessionHas('success');
    $this->assertDatabaseMissing('hr_vehicles', ['id' => $private->id]);
    $this->assertDatabaseHas('hr_vehicles', ['id' => $shared->id]);
    $this->assertDatabaseMissing('hr_leaves', ['user_id' => $user->id]);
    $this->assertDatabaseHas('users', ['id' => $other->id]);
});

test('permanent deletion requires archived records and confirmation', function () {
    $user = archiveAccount();
    $active = User::factory()->create();
    $active->assignRole(Role::findOrCreate('client_user'));
    $this->delete(route('settings.users.forceDestroy', $user))->assertSessionHasErrors('confirm_delete');
    $this->delete(route('settings.users.forceDestroy', $active), ['confirm_delete' => 1])->assertStatus(422);
    $company = Company::create(['name' => 'Aktywna']);
    $this->delete(route('settings.archive.companies.destroy', $company), ['confirm_delete' => 1])->assertSessionHas('error');
    $company->update(['archived_at' => now()]);
    $this->delete(route('settings.archive.companies.destroy', $company))->assertSessionHasErrors('confirm_delete');
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('companies', ['id' => $company->id]);
});

test('archive reader cannot delete records and admin cannot delete peer or superadmin', function () {
    $reader = User::factory()->create();
    $role = Role::findOrCreate('archive_reader');
    $role->givePermissionTo(Permission::findOrCreate('settings.archive.view'));
    $reader->assignRole($role);
    $user = archiveAccount();
    $company = Company::create(['name' => 'Archiwalna', 'archived_at' => now()]);
    $this->actingAs($reader)->get(route('settings.archive.index'))->assertOk()->assertDontSee('Usuń trwale');
    $this->delete(route('settings.users.forceDestroy', $user), ['confirm_delete' => 1])->assertForbidden();
    $this->delete(route('settings.archive.companies.destroy', $company), ['confirm_delete' => 1])->assertForbidden();
    $peer = archiveAccount('admin');
    $superadmin = archiveAccount('superadmin');
    $this->actingAs($this->admin)->delete(route('settings.users.forceDestroy', $peer), ['confirm_delete' => 1])->assertForbidden();
    $this->delete(route('settings.users.forceDestroy', $superadmin), ['confirm_delete' => 1])->assertSessionHas('error');
    $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
});

test('archive shows orphan accounts and sortable columns while keeping actions unsortable', function () {
    $user = User::factory()->create();
    $user->delete();
    $this->get(route('settings.archive.index'))->assertOk()->assertSee($user->email)->assertSee('Usuń trwale')
        ->assertSee('data-sortable="false"', false)->assertSee($user->deleted_at->format('Y-m-d H:i:s'));
});

test('company purge removes its complete history including soft deleted records and private files without deleting accounts or other companies', function () {
    $company = Company::create(['name' => 'Do usunięcia', 'archived_at' => now()]);
    $other = Company::create(['name' => 'Pozostaje']);
    $user = archiveAccount();
    $company->users()->attach($user->id, ['deleted_at' => now()]);
    $audit = Audit::create(['company_id' => $company->id, 'title' => 'Audyt', 'number' => 'ARC/A/1', 'status' => 'in_progress']);
    $project = Project::create(['company_id' => $company->id, 'name' => 'Projekt', 'number' => 'ARC/P/1', 'manager_id' => $this->admin->id]);
    $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'ARC/O/1', 'status' => 'w_toku']);
    $otherAudit = Audit::create(['company_id' => $other->id, 'title' => 'Pozostawiony audyt', 'number' => 'ARC/A/OTHER', 'status' => 'in_progress']);
    $otherProject = Project::create(['company_id' => $other->id, 'name' => 'Pozostawiony projekt', 'number' => 'ARC/P/OTHER', 'manager_id' => $this->admin->id]);
    $otherOffer = Offer::create(['company_id' => $other->id, 'offer_number' => 'ARC/O/OTHER', 'status' => 'w_toku']);
    $audit->delete();
    $project->delete();
    $offer->delete();
    foreach ([['company_id' => $company->id], ['company_id' => null, 'project_id' => $project->id]] as $i => $attributes) {
        $path = 'archive/file-'.$i.'.pdf';
        Storage::disk('local')->put($path, 'fake content');
        Document::create($attributes + ['original_filename' => 'file.pdf', 'stored_path' => $path, 'size' => 12, 'type' => 'upload']);
    }
    Storage::disk('local')->put('archive/shared.pdf', 'shared');
    foreach ([$company, $other] as $owner) {
        Document::create(['company_id' => $owner->id, 'original_filename' => 'shared.pdf', 'stored_path' => 'archive/shared.pdf', 'size' => 6, 'type' => 'upload']);
    }
    $this->delete(route('settings.archive.companies.destroy', $company), ['confirm_delete' => 1])->assertSessionHas('success');
    foreach (['companies' => $company->id, 'audits' => $audit->id, 'projects' => $project->id, 'offers' => $offer->id] as $table => $id) {
        $this->assertDatabaseMissing($table, ['id' => $id]);
    }
    $this->assertDatabaseHas('companies', ['id' => $other->id]);
    $this->assertDatabaseHas('audits', ['id' => $otherAudit->id]);
    $this->assertDatabaseHas('projects', ['id' => $otherProject->id]);
    $this->assertDatabaseHas('offers', ['id' => $otherOffer->id]);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    Storage::disk('local')->assertMissing('archive/file-0.pdf');
    Storage::disk('local')->assertMissing('archive/file-1.pdf');
    Storage::disk('local')->assertExists('archive/shared.pdf');
});

test('company owner is protected and failed purge rolls back rows without removing files', function () {
    $owner = Company::create(['name' => 'Właściciel', 'is_owner' => true, 'archived_at' => now()]);
    $this->delete(route('settings.archive.companies.destroy', $owner), ['confirm_delete' => 1])->assertSessionHas('error');
    $company = Company::create(['name' => 'Ograniczona', 'archived_at' => now()]);
    $cylinder = Cylinder::create(['company_id' => $company->id, 'type' => 'Butla', 'serial_number' => 'ARC-001']);
    $series = CylinderInspectionSeries::create(['name' => 'Obca seria', 'company_id' => $owner->id, 'created_by' => $this->admin->id, 'responsible_id' => $this->admin->id]);
    $series->items()->create(['cylinder_id' => $cylinder->id]);
    Storage::disk('local')->put('archive/keep.pdf', 'keep');
    $document = Document::create(['company_id' => $company->id, 'original_filename' => 'keep.pdf', 'stored_path' => 'archive/keep.pdf', 'size' => 4, 'type' => 'upload']);
    $this->from(route('settings.archive.index'))->delete(route('settings.archive.companies.destroy', $company), ['confirm_delete' => 1])->assertSessionHasErrors('company');
    $this->assertDatabaseHas('documents', ['id' => $document->id]);
    Storage::disk('local')->assertExists('archive/keep.pdf');
});

test('company purge removes its device history in dependency order including stored protocols', function () {
    $company = Company::create(['name' => 'Urządzenia', 'archived_at' => now()]);
    $device = Cylinder::create(['company_id' => $company->id, 'type' => 'Butla', 'serial_number' => 'ARC-DEV-1']);
    $entry = $device->inspections()->create(['inspected_at' => '2026-10-01', 'result' => 'no_findings', 'observations' => 'Test', 'inspector_name' => 'Test']);
    $series = CylinderInspectionSeries::create(['name' => 'Seria własna', 'company_id' => $company->id, 'created_by' => $this->admin->id, 'responsible_id' => $this->admin->id]);
    $series->items()->create(['cylinder_id' => $device->id, 'inspection_id' => $entry->id]);
    DB::table('cylinder_protocols')->insert(['series_id' => $series->id, 'number' => 'ARC/2026/1', 'issued_by' => $this->admin->id, 'issued_at' => now(), 'template_version' => '1', 'snapshot' => '{}', 'stored_path' => 'archive/protocol.pdf', 'sha256' => str_repeat('a', 64)]);
    DB::table('cylinder_imports')->insert(['company_id' => $company->id, 'series_id' => $series->id, 'user_id' => $this->admin->id, 'token' => (string) str()->uuid(), 'rows' => '[]', 'expires_at' => now()->addDay()]);
    Storage::disk('local')->put('archive/protocol.pdf', 'pdf');
    $this->delete(route('settings.archive.companies.destroy', $company), ['confirm_delete' => 1])->assertSessionHas('success');
    foreach (['cylinders', 'cylinder_inspections', 'cylinder_protocols', 'cylinder_imports', 'cylinder_inspection_series_items', 'cylinder_inspection_series'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    Storage::disk('local')->assertMissing('archive/protocol.pdf');
});

test('unexpected restrictive links roll back database deletion and preserve files', function () {
    Schema::create('archive_test_company_reference', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
    });
    try {
        $company = Company::create(['name' => 'Chroniona zależność', 'archived_at' => now()]);
        DB::table('archive_test_company_reference')->insert(['company_id' => $company->id]);
        Storage::disk('local')->put('archive/rollback.pdf', 'keep');
        $doc = Document::create(['company_id' => $company->id, 'original_filename' => 'file.pdf', 'stored_path' => 'archive/rollback.pdf', 'size' => 4, 'type' => 'upload']);
        $this->delete(route('settings.archive.companies.destroy', $company), ['confirm_delete' => 1])->assertSessionHas('error');
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('documents', ['id' => $doc->id]);
        Storage::disk('local')->assertExists('archive/rollback.pdf');
    } finally {
        Schema::dropIfExists('archive_test_company_reference');
    }
});

test('protected user references preserve the account and authentication data on failure', function () {
    $user = archiveAccount('auditor');
    $series = CylinderInspectionSeries::create(['name' => 'Historia pracownika', 'created_by' => $user->id, 'responsible_id' => $user->id]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'keep-token']);
    $this->from(route('settings.archive.index'))->delete(route('settings.users.forceDestroy', $user), ['confirm_delete' => 1])->assertSessionHas('error');
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseHas('cylinder_inspection_series', ['id' => $series->id]);
});
