<?php

use App\Models\Audit;
use App\Models\Company;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('only platform administrators can remove audits and tasks while keeping their records', function (string $role) {
    Storage::fake('local');
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role));
    $user->givePermissionTo([Permission::findOrCreate('audits.view'), Permission::findOrCreate('audits.manage'), Permission::findOrCreate('crm.view')]);
    $company = Company::create(['name' => 'Firma', 'company_type' => 'client', 'status' => 'active']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'DEL/1', 'title' => 'Do usunięcia', 'status' => 'draft']);
    $task = Task::create(['audit_id' => $audit->id, 'company_id' => $company->id, 'title' => 'Zaległe', 'due_date' => '2020-01-01', 'status' => 'todo']);
    Storage::disk('local')->put('audits/example.txt', 'Dokument');
    $document = Document::create(['company_id' => $company->id, 'audit_id' => $audit->id, 'type' => 'other', 'original_filename' => 'example.txt', 'stored_path' => 'audits/example.txt', 'mime_type' => 'text/plain', 'size' => 8, 'uploaded_by' => $user->id]);
    $allowed = in_array($role, ['admin', 'superadmin']);
    $page = $this->actingAs($user)->get(route('companies.show', $company))->assertOk();
    if ($allowed) {
        $page->assertSee(route('audits.destroy', $audit), false)->assertSee('Usuń audyt');
        $this->delete(route('audits.destroy', $audit))->assertSessionHasErrors('confirm_delete');
        expect($audit->fresh()->deleted_at)->toBeNull();
    } else {
        $page->assertDontSee('Usuń audyt');
    }
    $response = $this->delete(route('audits.destroy', $audit), ['confirm_delete' => 1]);
    if ($allowed) {
        $response->assertRedirect();
        $this->assertSoftDeleted('audits', ['id' => $audit->id]);
        $this->assertSoftDeleted('tasks', ['id' => $task->id, 'deleted_by' => $user->id]);
        expect(Task::overdue()->whereKey($task->id)->exists())->toBeFalse();
        $this->get(route('audits.show', $audit))->assertNotFound();
        expect($company->audits()->count())->toBe(0);
        expect($document->fresh()->audit_id)->toBe($audit->id);
        Storage::disk('local')->assertExists('audits/example.txt');
    } else {
        $response->assertForbidden();
        expect(Audit::whereKey($audit->id)->exists())->toBeTrue()->and(Task::whereKey($task->id)->exists())->toBeTrue();
    }
})->with(['admin', 'superadmin', 'employee', 'audit_manager']);

test('administrator deletion still respects delegated company access', function () {
    $user = User::factory()->create();
    $adminRole = Role::findOrCreate('admin');
    $adminRole->syncPermissions([]);
    $auditorRole = Role::findOrCreate('auditor');
    $auditorRole->syncPermissions([]);
    $user->assignRole([$adminRole, $auditorRole]);
    $user->givePermissionTo(Permission::findOrCreate('audits.view'));
    $company = Company::create(['name' => 'Poza dostępem', 'company_type' => 'client', 'status' => 'active']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'DENY/1', 'title' => 'Audyt', 'status' => 'draft']);
    $this->actingAs($user)->delete(route('audits.destroy', $audit), ['confirm_delete' => 1])->assertForbidden();
    expect($audit->fresh()->deleted_at)->toBeNull();
});
