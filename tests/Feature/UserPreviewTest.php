<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userPreviewFixture(): array
{
    $super = User::factory()->create(['is_active' => true]);
    $super->assignRole(Role::findOrCreate('superadmin'));
    $worker = User::factory()->create(['is_active' => true]);
    $worker->assignRole(Role::findOrCreate('employee'));
    $worker->givePermissionTo('crm.tasks.own.manage');

    return [$super, $worker];
}

test('superadmin previews the real user scopes without changing the authenticated session and can return even from denied pages', function () {
    [$super, $worker] = userPreviewFixture();
    $list = $this->actingAs($super)->get(route('settings.users.index'))->assertOk()->assertSee('Podgląd jako użytkownik: '.$worker->name)->assertDontSee('Podgląd jako użytkownik: '.$super->name);
    Task::create(['title' => 'Widoczne pracownikowi', 'assigned_to' => $worker->id, 'status' => 'todo']);
    Task::create(['title' => 'Wyłącznie administratora', 'assigned_to' => $super->id, 'status' => 'todo']);
    $this->actingAs($super)->post(route('user-preview.start', $worker), ['reason' => 'Weryfikacja zgłoszenia'])->assertRedirect(route('board.mine'));
    $preview = $this->get(route('board.mine'))->assertOk()->assertSee('Widoczne pracownikowi')->assertDontSee('Wyłącznie administratora')->assertSee('Wróć do superadministratora')->assertSee($worker->email);
    if (getenv('USER_PREVIEW_QA')) {
        file_put_contents(base_path('tmp/user-preview-list.html'), $list->getContent());
        file_put_contents(base_path('tmp/user-preview-board.html'), $preview->getContent());
    }
    $this->assertAuthenticatedAs($super);
    $this->get(route('settings.users.index'))->assertForbidden()->assertSee('Wróć do superadministratora');
    $this->post(route('user-preview.stop'))->assertRedirect(route('settings.users.index'))->assertSessionMissing('support_user_preview');
    $this->get(route('board.mine'))->assertOk()->assertSee('Wyłącznie administratora')->assertDontSee('Wróć do superadministratora');
    expect(ActivityLog::where('user_id', $super->id)->where('subject_label', 'like', '%podgląd serwisowy%')->count())->toBe(2);
});

test('preview blocks writes nesting account changes and inherited client zone sessions', function () {
    [$super, $worker] = userPreviewFixture();
    $task = Task::create(['title' => 'Bez zmiany', 'assigned_to' => $worker->id, 'status' => 'todo']);
    $this->actingAs($super)->withSession(['client_zone_company_id' => 123])->post(route('user-preview.start', $worker), ['reason' => 'Kontrola widoczności'])->assertRedirect()->assertSessionMissing('client_zone_company_id');
    $this->patchJson(route('crm.tasks.status', $task), ['status' => 'done'])->assertForbidden();
    $this->post(route('user-preview.start', $worker), ['reason' => 'Zagnieżdżenie'])->assertForbidden();
    $this->get(route('client-zone.dashboard'))->assertForbidden();
    expect($task->fresh()->status)->toBe('todo');
    $this->post(route('user-preview.stop'))->assertRedirect();
    $this->patchJson(route('crm.tasks.status', $task), ['status' => 'done'])->assertOk();
});

test('only superadmin can start and blocked deleted or superadmin targets cannot be previewed', function () {
    [$super, $worker] = userPreviewFixture();
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $this->actingAs($admin)->post(route('user-preview.start', $worker), ['reason' => 'Sprawdzenie'])->assertForbidden();
    $this->actingAs($super)->post(route('user-preview.start', $super), ['reason' => 'Sprawdzenie'])->assertStatus(422);
    $this->post(route('user-preview.start', $worker), [])->assertSessionHasErrors('reason');
    $worker->update(['is_active' => false]);
    $this->post(route('user-preview.start', $worker), ['reason' => 'Sprawdzenie'])->assertStatus(422);
    $worker->delete();
    $this->post(route('user-preview.start', $worker), ['reason' => 'Sprawdzenie'])->assertNotFound();
});

test('preview expires or ends when target access is revoked and cannot be reused by a different actor', function () {
    [$super, $worker] = userPreviewFixture();
    $this->actingAs($super)->post(route('user-preview.start', $worker), ['reason' => 'Sprawdzenie']);
    $this->travel(31)->minutes();
    $this->get(route('board.mine'))->assertRedirect(route('settings.users.index'))->assertSessionMissing('support_user_preview');
    $this->travelBack();
    $this->post(route('user-preview.start', $worker), ['reason' => 'Sprawdzenie']);
    $worker->forceFill(['session_version' => $worker->session_version + 1])->save();
    $this->get(route('board.mine'))->assertRedirect(route('settings.users.index'))->assertSessionMissing('support_user_preview');
    $this->post(route('user-preview.start', $worker), ['reason' => 'Sprawdzenie']);
    $this->actingAs($worker)->withSession(['security_version' => (int) $worker->session_version])->get(route('board.mine'))->assertForbidden()->assertSessionMissing('support_user_preview');
});

test('superadmin MFA remains required and client preview uses client access without altering target security activity', function () {
    [$super, $worker] = userPreviewFixture();
    config(['security.totp.enabled' => true]);
    $this->actingAs($super)->post(route('user-preview.start', $worker), ['reason' => 'Sprawdzenie'])->assertRedirect(route('auth.totp'))->assertSessionMissing('support_user_preview');
    config(['security.totp.enabled' => false]);
    $client = User::factory()->create(['security_activity_at' => now()->subDay()]);
    $client->assignRole(Role::findOrCreate('client_user'));
    $company = Company::create(['name' => 'Klient serwisowy', 'company_type' => 'client', 'status' => 'active']);
    $client->companies()->attach($company);
    $activity = $client->security_activity_at->toIso8601String();
    $this->post(route('user-preview.start', $client), ['reason' => 'Zgłoszenie klienta'])->assertRedirect(route('client.dashboard'));
    $this->get(route('client.dashboard'))->assertOk()->assertSee('Wróć do superadministratora');
    expect($client->fresh()->security_activity_at->toIso8601String())->toBe($activity);
    $this->post(route('logout'))->assertRedirect('/')->assertSessionMissing('support_user_preview');
    $this->assertGuest();
});

test('preview does not persist database side effects on GET pages', function () {
    [$super, $worker] = userPreviewFixture();
    $worker->givePermissionTo(Permission::findOrCreate('dashboard.view'));
    Task::create(['title' => 'Powiadomienie do odczytu', 'assigned_to' => $worker->id, 'status' => 'todo']);
    $before = $worker->fresh()->dashboard_tasks_seen_id;
    $this->actingAs($super)->post(route('user-preview.start', $worker), ['reason' => 'Kontrola alertów']);
    $this->get(route('dashboard'))->assertOk()->assertSee('Wróć do superadministratora');
    expect($worker->fresh()->dashboard_tasks_seen_id)->toBe($before);
    $this->post(route('user-preview.stop'))->assertRedirect();
});

test('preview logs downloads as the real administrator and refuses deferred streaming exports', function () {
    [$super, $worker] = userPreviewFixture();
    Route::middleware(['web', 'auth'])->get('/preview-download-fixture', fn () => response('document')->header('Content-Disposition', 'attachment; filename=test.txt'))->name('preview-fixture.download');
    Route::middleware(['web', 'auth'])->get('/preview-stream-fixture', fn () => response()->streamDownload(fn () => print ('never executed'), 'export.txt'));
    $this->actingAs($super)->post(route('user-preview.start', $worker), ['reason' => 'Weryfikacja pobierania']);
    $this->get('/preview-download-fixture')->assertOk();
    expect(ActivityLog::where('action', 'download')->where('route_name', 'preview-fixture.download')->sole()->user_id)->toBe($super->id);
    $this->get('/preview-stream-fixture')->assertForbidden()->assertSee('Wróć do superadministratora');
    $this->post(route('user-preview.stop'))->assertRedirect();
});

test('revoking the administrator session also revokes the preview', function () {
    [$super, $worker] = userPreviewFixture();
    $this->actingAs($super)->post(route('user-preview.start', $worker), ['reason' => 'Weryfikacja zgłoszenia']);
    $super->forceFill(['session_version' => $super->session_version + 1])->save();
    $this->get(route('board.mine'))->assertRedirect(route('login'))->assertSessionMissing('support_user_preview');
    $this->assertGuest();
});

test('route bindings also resolve resources using the previewed user rather than the administrator', function () {
    [$super, $worker] = userPreviewFixture();
    $own = Task::create(['title' => 'Pracownik', 'assigned_to' => $worker->id, 'status' => 'todo']);
    $foreign = Task::create(['title' => 'Administrator', 'assigned_to' => $super->id, 'status' => 'todo']);
    Route::bind('previewRecord', fn ($id) => Task::forUser(auth()->id())->findOrFail($id));
    Route::middleware(['web', 'auth'])->get('/preview-binding-fixture/{previewRecord}', fn (Task $previewRecord) => response($previewRecord->title));
    $this->actingAs($super)->post(route('user-preview.start', $worker), ['reason' => 'Kontrola zakresów']);
    $this->get('/preview-binding-fixture/'.$own->id)->assertOk()->assertSee('Pracownik');
    $this->get('/preview-binding-fixture/'.$foreign->id)->assertNotFound();
    $this->post(route('user-preview.stop'))->assertRedirect();
});
