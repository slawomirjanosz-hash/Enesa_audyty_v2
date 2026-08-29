<?php

use App\Models\Audit;
use App\Models\AuditFinancialEntry;
use App\Models\AuditSurvey;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\EnergyPassport;
use App\Models\EnergyPassportTemplate;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function auditManager(): User
{
    $user = User::factory()->create();
    $role = Role::findOrCreate('audit_manager');
    $role->givePermissionTo([Permission::findOrCreate('crm.view'), Permission::findOrCreate('audits.view'), Permission::findOrCreate('audits.manage')]);
    $user->assignRole($role);

    return $user;
}

test('audit is created from company card and opens the dedicated workspace', function () {
    $user = auditManager();
    $company = Company::create(['name' => 'Fabryka Audytowana', 'company_type' => 'client', 'status' => 'active']);

    $this->actingAs($user)->get(route('companies.show', $company))
        ->assertOk()->assertSee('Dodaj audyt')->assertSee(route('audits.store'), false);

    $this->actingAs($user)->post(route('audits.store'), [
        'company_id' => $company->id, 'number' => 'AUD/2026/001', 'title' => 'Audyt energetyczny zakładu',
        'manager_id' => $user->id, 'member_ids' => [$user->id], 'status' => 'draft',
        'start_date' => '2026-09-01', 'end_date' => '2026-10-01', 'contract_value' => 25000,
    ])->assertRedirect();

    $audit = Audit::firstOrFail();
    $this->actingAs($user)->get(route('audits.show', $audit))->assertOk()
        ->assertSee('Harmonogram i zadania')->assertSee('Finanse')->assertSee('Dokumenty')
        ->assertSee('Ankiety Audytowe')->assertSee('Paszporty Energetyczne')
        ->assertSee('Edytuj audyt')->assertSee('Osoby przypisane do audytu')
        ->assertSee('id="project-frappe-gantt"', false)->assertSee('Dodaj kamień milowy')
        ->assertSee('Eksport Excel')->assertSee('Import Excel');
});

test('audit workspace stores tasks finances surveys passports and documents outside CRM', function () {
    Storage::fake('local');
    $user = auditManager();
    $company = Company::create(['name' => 'Zakład ISO', 'company_type' => 'client', 'status' => 'active']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'AUD/2026/002', 'title' => 'Audyt ISO', 'manager_id' => $user->id, 'status' => 'in_progress', 'created_by' => $user->id]);
    $audit->members()->attach($user);

    $this->actingAs($user)->post(route('audits.tasks.store', $audit), ['title' => 'Pomiary', 'assigned_to' => $user->id, 'start_date' => '2026-09-01', 'due_date' => '2026-09-03', 'status' => 'todo', 'priority' => 'high', 'progress' => 0])->assertSessionHas('success');
    $this->actingAs($user)->post(route('audits.finances.store', $audit), ['type' => 'cost', 'name' => 'Pomiary elektryczne', 'entry_date' => '2026-09-01', 'amount' => 1500, 'status' => 'planned'])->assertSessionHas('success');
    $task = Task::firstOrFail();
    $this->actingAs($user)->put(route('audits.tasks.update', [$audit, $task]), ['title' => 'Pomiary po zmianie', 'assigned_to' => $user->id, 'start_date' => '2026-09-01', 'due_date' => '2026-09-04', 'status' => 'in_progress', 'priority' => 'medium', 'progress' => 40])->assertSessionHas('success');
    $this->actingAs($user)->patchJson(route('audits.tasks.update', [$audit, $task]), ['progress' => 50])->assertOk()->assertJsonPath('progress', 50);
    $finance = AuditFinancialEntry::firstOrFail();
    $this->actingAs($user)->put(route('audits.finances.update', [$audit, $finance]), ['type' => 'cost', 'name' => 'Pomiary po zmianie', 'entry_date' => '2026-09-01', 'amount' => 1800, 'status' => 'issued'])->assertSessionHas('success');
    $auditType = AuditType::create(['name' => 'Ankieta utrzymania ruchu', 'slug' => 'utrzymanie-ruchu']);
    $this->actingAs($user)->post(route('audits.surveys.store', $audit), ['audit_type_id' => $auditType->id, 'status' => 'draft'])->assertSessionHas('success');
    $template = EnergyPassportTemplate::firstOrFail();
    $this->actingAs($user)->post(route('audits.passports.store', $audit), ['template_id' => $template->id, 'name' => 'Paszport AHU-01', 'asset_identifier' => 'AHU-01'])->assertRedirect();
    $this->actingAs($user)->post(route('audits.documents.store', $audit), ['file' => UploadedFile::fake()->create('protokol.pdf', 20, 'application/pdf')])->assertSessionHas('success');

    expect(Task::firstOrFail()->audit_id)->toBe($audit->id)
        ->and(Task::crm()->count())->toBe(0)
        ->and($task->fresh()->progress)->toBe(50)
        ->and(AuditFinancialEntry::count())->toBe(1)
        ->and((float) $finance->fresh()->amount)->toBe(1800.0)
        ->and(AuditSurvey::firstOrFail()->audit_type_id)->toBe($auditType->id)
        ->and(AuditSurvey::firstOrFail()->title)->toBe($auditType->name)
        ->and(EnergyPassport::firstOrFail()->audit_id)->toBe($audit->id)
        ->and($audit->documents()->count())->toBe(1);
});

test('client sees audits assigned to their company in the client zone', function () {
    $company = Company::create(['name' => 'Klient z audytem', 'company_type' => 'client', 'status' => 'active']);
    $otherCompany = Company::create(['name' => 'Inny klient', 'company_type' => 'client', 'status' => 'active']);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->companies()->attach($company, ['is_admin' => false]);
    $clientAudit = Audit::create(['company_id' => $company->id, 'number' => 'AUD/KLIENT/1', 'title' => 'Audyt widoczny dla klienta', 'status' => 'draft', 'contract_value' => 987654.32]);
    $otherAudit = Audit::create(['company_id' => $otherCompany->id, 'number' => 'AUD/OBCY/1', 'title' => 'Audyt innej firmy', 'status' => 'draft']);

    $this->actingAs($client)->get(route('client.audits'))->assertOk()
        ->assertSee('Audyt widoczny dla klienta')
        ->assertSee('AUD/KLIENT/1')
        ->assertDontSee('Audyt innej firmy');
    $this->actingAs($client)->get(route('client.dashboard'))->assertOk()
        ->assertSee(route('client.audits.show', $clientAudit), false);

    $this->actingAs($client)->get(route('client.audits.show', $clientAudit))->assertOk()
        ->assertSee('Harmonogram i zadania')->assertSee('Dokumenty')
        ->assertSee('Ankiety Audytowe')->assertSee('Paszporty Energetyczne')
        ->assertDontSee('>Finanse<', false)->assertDontSee('987 654,32');
    $this->actingAs($client)->get(route('client.audits.show', $otherAudit))->assertNotFound();
});
