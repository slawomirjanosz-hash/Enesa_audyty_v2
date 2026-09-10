<?php

use App\Mail\TaskOverdue;
use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\Company;
use App\Models\Document;
use App\Models\IsoSectionDocument;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\DocumentVersionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function consistencyUser(array $permissions = []): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::findOrCreate('review_staff'));
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission));
    }

    return $user;
}

test('global document permissions do not bypass project membership or document permissions', function () {
    Storage::fake('local');
    $user = consistencyUser(['documents.view', 'documents.delete', 'projects.view']);
    $company = Company::create(['name' => 'Restricted']);
    $project = Project::create(['company_id' => $company->id, 'number' => 'SEC/1', 'name' => 'Restricted project', 'status' => 'active']);
    $document = Document::create(['project_id' => $project->id, 'company_id' => $company->id, 'type' => 'upload', 'original_filename' => 'secret.pdf', 'stored_path' => 'secret.pdf', 'size' => 5]);
    Storage::disk('local')->put('secret.pdf', 'secret');
    $this->actingAs($user)->get(route('documents.index'))->assertOk()->assertDontSee('secret.pdf');
    $this->get(route('documents.download', $document))->assertForbidden();
    expect(Gate::forUser($user)->allows('delete', $document))->toBeFalse();
    $project->members()->attach($user);
    expect(Gate::forUser($user)->allows('view', $document))->toBeFalse();
    $user->givePermissionTo(Permission::findOrCreate('projects.documents.view'));
    expect(Gate::forUser($user)->allows('view', $document->fresh()))->toBeTrue();
});

test('document pagination searches beyond the first page and retains total size', function () {
    $user = consistencyUser(['documents.view']);
    $company = Company::create(['name' => 'Documents']);
    for ($i = 0; $i < 55; $i++) {
        Document::create(['company_id' => $company->id, 'type' => 'upload', 'original_filename' => "item-$i.pdf", 'stored_path' => "item-$i.pdf", 'size' => 1024]);
    }
    $this->actingAs($user)->get(route('documents.index'))->assertOk()->assertViewHas('documentPage', fn ($page) => $page->count() === 50 && $page->total() === 55)->assertSee('55 KB');
    $this->get(route('documents.index', ['q' => 'item-0.pdf']))->assertOk()->assertSee('item-0.pdf')->assertViewHas('documentPage', fn ($page) => $page->total() === 1);
});

test('ISO document metadata excludes payload and retrieves it only when requested', function () {
    Storage::fake('local');
    $document = IsoSectionDocument::create(['section_id' => '4-1', 'scope' => 'template', 'title' => 'Test', 'version_number' => '1.0', 'original_filename' => 'test.pdf', 'stored_path' => 'missing.pdf', 'mime_type' => 'application/pdf', 'size' => 4, 'content_base64' => base64_encode('test')]);
    $metadata = IsoSectionDocument::metadata()->findOrFail($document->id);
    expect(array_key_exists('content_base64', $metadata->getAttributes()))->toBeFalse()
        ->and($metadata->isAvailable())->toBeTrue()
        ->and($metadata->contents())->toBe('test');
});

test('document version counter never reuses a reserved version', function () {
    $service = app(DocumentVersionService::class);
    expect($service->next(123, '4-1', 'mime_type', 'application/pdf'))->toBe('1.0')
        ->and($service->next(123, '4-1', 'mime_type', 'application/pdf'))->toBe('2.0')
        ->and($service->next(123, '4-1', 'mime_type', 'application/pdf'))->toBe('3.0')
        ->and($service->next(124, '4-1', 'mime_type', 'application/pdf'))->toBe('1.0');
});

test('overdue reminders exclude removed projects and revoked access', function () {
    Mail::fake();
    $user = consistencyUser(['projects.view', 'projects.schedule.view']);
    $company = Company::create(['name' => 'Tasks']);
    $project = Project::create(['company_id' => $company->id, 'number' => 'TASK/1', 'name' => 'Project', 'status' => 'active']);
    Task::create(['project_id' => $project->id, 'assigned_to' => $user->id, 'title' => 'Hidden task', 'due_date' => now()->subDay(), 'status' => 'todo']);
    $this->artisan('tasks:send-overdue-reminders')->assertSuccessful();
    Mail::assertNothingSent();
    $project->members()->attach($user);
    $this->artisan('tasks:send-overdue-reminders')->assertSuccessful();
    Mail::assertSent(TaskOverdue::class, 1);
    Mail::fake();
    $project->delete();
    $this->artisan('tasks:send-overdue-reminders')->assertSuccessful();
    Mail::assertNothingSent();
});

test('two uploads with identical names keep distinct files', function () {
    Storage::fake('local');
    $user = consistencyUser(['system.full_access', 'documents.upload']);
    $company = Company::create(['name' => 'Uploads']);
    foreach (['first content', 'second content'] as $content) {
        $this->actingAs($user)->post(route('documents.store'), ['company_id' => $company->id,
            'file' => UploadedFile::fake()->createWithContent('same.pdf', "%PDF-1.4\n".$content),
        ])->assertSessionHasNoErrors()->assertRedirect();
    }
    $documents = Document::where('company_id', $company->id)->get();
    expect($documents)->toHaveCount(2)->and($documents->pluck('stored_path')->unique())->toHaveCount(2);
    expect(Storage::disk('local')->get($documents[0]->stored_path))->toContain('first content');
    expect(Storage::disk('local')->get($documents[1]->stored_path))->toContain('second content');
});

test('bulk finance updates and deletions are recorded in activity history', function () {
    $user = consistencyUser(['system.full_access']);
    $company = Company::create(['name' => 'History']);
    $project = Project::create(['company_id' => $company->id, 'number' => 'LOG/1', 'name' => 'History project', 'status' => 'active']);
    $entry = $project->financialEntries()->create(['name' => 'Invoice', 'type' => 'cost', 'source' => 'manual', 'amount' => 100, 'status' => 'planned', 'entry_date' => '2026-09-10']);
    foreach (['paid', 'delete'] as $action) {
        $this->actingAs($user)->post(route('projects.finances.bulk', $project), ['entry_ids' => [$entry->id], 'action' => $action])->assertRedirect();
        expect(ActivityLog::where('auditable_type', $entry::class)->where('auditable_id', $entry->id)->where('action', $action === 'delete' ? 'deleted' : 'updated')->where('user_id', $user->id)->exists())->toBeTrue();
    }
});

test('branding is queried once for multiple partials in a request', function () {
    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'company_settings')) {
            $queries++;
        }
    });
    request()->attributes->remove('app_brand_loaded');
    foreach ([403, 404, 500] as $status) {
        view('errors.'.$status)->render();
    }
    expect($queries)->toBe(1);
});

test('bulk audit task deletion records history and clears dependencies', function () {
    $user = consistencyUser(['system.full_access']);
    $company = Company::create(['name' => 'Audit history']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'LOG/A', 'title' => 'Audit', 'status' => 'draft']);
    $task = $audit->tasks()->create(['title' => 'Remove', 'status' => 'todo']);
    $dependent = $audit->tasks()->create(['title' => 'Keep', 'status' => 'todo', 'depends_on_task_id' => $task->id]);
    $this->actingAs($user)->deleteJson(route('audits.tasks.bulk-destroy', $audit), ['task_ids' => [$task->id]])->assertOk();
    expect($dependent->fresh()->depends_on_task_id)->toBeNull();
    expect(ActivityLog::where('auditable_type', Task::class)->where('auditable_id', $task->id)->where('action', 'deleted')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('version allocation starts above existing versions and survives their deletion', function () {
    $company = Company::create(['name' => 'Versions']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'VER/A', 'title' => 'Audit', 'status' => 'draft']);
    $document = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => '4-1', 'scope' => 'client', 'title' => 'Test', 'version_number' => '9.0', 'original_filename' => 'test.pdf', 'stored_path' => 'test.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);
    $service = app(DocumentVersionService::class);
    expect($service->next($audit->id, '4-1', 'mime_type', 'application/pdf'))->toBe('10.0');
    $document->delete();
    expect($service->next($audit->id, '4-1', 'mime_type', 'application/pdf'))->toBe('11.0');
});
