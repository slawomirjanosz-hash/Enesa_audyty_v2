<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\User;
use App\Services\CylinderPhotoRenderer;
use App\Services\DocumentQuotaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    CompanySettings::create(['name' => 'Test', 'enabled_modules' => ['cylinders', 'client_zone']]);
    $this->operator = User::factory()->create();
    $this->operator->assignRole(Role::findOrCreate('superadmin'));
    $this->device = Cylinder::create(['company_id' => Company::create(['name' => 'Klient', 'company_type' => 'client'])->id, 'type' => 'Butla', 'serial_number' => 'TEST']);
    $this->entryData = ['inspected_at' => '2026-01-02', 'result' => 'no_findings', 'observations' => 'Sprawdzono', 'weight_kg' => '12,125', 'working_pressure_bar' => '200'];
    $this->actingAs($this->operator);
});

test('inspection saves measurements one private movie and multiple photos preserving old history', function () {
    $this->post(route('cylinders.inspections.store', $this->device), $this->entryData + [
        'inspection_video' => UploadedFile::fake()->create('film.mov', 1, 'video/quicktime'),
        'inspection_photos' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.png')],
    ])->assertSessionHasNoErrors();
    $entry = $this->device->inspections()->sole();
    expect((float) $entry->weight_kg)->toBe(12.125)->and($entry->videos()->count())->toBe(1)->and($entry->photos()->count())->toBe(2)
        ->and($this->device->photo)->toBeNull()->and($entry->inspector_id)->toBe($this->operator->id);
    foreach ($entry->photos as $photo) {
        Storage::disk('local')->assertExists([$photo->stored_path, $photo->thumbnail_path]);
        $this->get(route('cylinders.inspections.photos.show', [$this->device, $entry, $photo]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }
    expect(app(DocumentQuotaService::class)->used($this->operator->id))->toBeGreaterThan(0);
    $this->get(route('cylinders.inspections.media', [$this->device, $entry]))->assertOk()->assertSee('<video', false)->assertSee('data-inspection-image', false);
    $this->get(route('cylinders.index'))->assertOk()->assertSee('data-inspection-media', false);
    $this->get(route('cylinders.inspections.edit', [$this->device, $entry]))->assertOk()->assertSee('Film jest już dołączony');
    $this->post(route('cylinders.inspections.store', $this->device), array_replace($this->entryData, ['inspected_at' => '2026-01-03']))->assertSessionHasNoErrors();
    expect($this->device->inspections()->count())->toBe(2)->and($entry->fresh()->photos()->count())->toBe(2);
    $this->get(route('cylinders.index'))->assertOk()->assertDontSee('data-inspection-media ', false);
});

test('inspection edits append photos reject second movies and stale revisions without overwriting', function () {
    $this->post(route('cylinders.inspections.store', $this->device), $this->entryData + ['inspection_video' => UploadedFile::fake()->create('film.mp4', 1, 'video/mp4')])->assertSessionHasNoErrors();
    $entry = $this->device->inspections()->sole();
    $this->put(route('cylinders.inspections.update', [$this->device, $entry]), $this->entryData + ['revision' => 1, 'inspection_photos' => [UploadedFile::fake()->image('new.png')]])->assertSessionHasNoErrors();
    expect($entry->fresh()->revision)->toBe(2)->and($entry->photos()->count())->toBe(1);
    $this->put(route('cylinders.inspections.update', [$this->device, $entry]), $this->entryData + ['revision' => 1])->assertStatus(409);
    $this->put(route('cylinders.inspections.update', [$this->device, $entry]), $this->entryData + ['revision' => 2, 'inspection_video' => UploadedFile::fake()->create('new.mov', 1, 'video/quicktime')])->assertSessionHasErrors('inspection_video');
    $this->post(route('cylinders.videos.store', $this->device), ['cylinder_inspection_id' => $entry->id, 'title' => 'Second', 'source' => 'link', 'external_url' => 'https://youtu.be/abcdefghijk'])->assertSessionHasErrors('file');
    expect($entry->fresh()->revision)->toBe(2)->and($entry->videos()->count())->toBe(1);
});

test('inspection upload validates limits and cleans all files on processing failure', function () {
    config(['cylinders.video_max_kb' => 1, 'cylinders.photo_max_kb' => 1]);
    $this->post(route('cylinders.inspections.store', $this->device), $this->entryData + ['inspection_video' => UploadedFile::fake()->create('huge.mp4', 2, 'video/mp4')])->assertSessionHasErrors('inspection_video');
    $this->post(route('cylinders.inspections.store', $this->device), $this->entryData + ['inspection_photos' => [UploadedFile::fake()->image('huge.jpg')->size(2)]])->assertSessionHasErrors('inspection_photos.0');
    $this->post(route('cylinders.inspections.store', $this->device), $this->entryData + ['inspection_video' => UploadedFile::fake()->createWithContent('fake.mp4', '<html>bad</html>')])->assertSessionHasErrors('inspection_video');
    config(['cylinders.photo_max_kb' => 8192]);
    $this->mock(CylinderPhotoRenderer::class)->shouldReceive('render')->once()->andThrow(ValidationException::withMessages(['photo' => 'Bad image']));
    $this->post(route('cylinders.inspections.store', $this->device), $this->entryData + ['inspection_video' => UploadedFile::fake()->create('ok.mp4', 1, 'video/mp4'), 'inspection_photos' => [UploadedFile::fake()->image('bad.jpg')]])->assertSessionHasErrors('inspection_photos.0');
    expect($this->device->inspections()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('inspection media is isolated by device company and module permissions', function () {
    $this->post(route('cylinders.inspections.store', $this->device), $this->entryData + ['inspection_photos' => [UploadedFile::fake()->image('photo.jpg')]])->assertSessionHasNoErrors();
    $entry = $this->device->inspections()->sole();
    $photo = $entry->photos()->sole();
    $other = Cylinder::create(['type' => 'Other', 'serial_number' => 'OTHER']);
    $this->get(route('cylinders.inspections.media', [$other, $entry]))->assertNotFound();
    $this->get(route('cylinders.inspections.photos.show', [$other, $entry, $photo]))->assertNotFound();
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $this->actingAs($client)->get(route('client.cylinders.inspections.media', [$this->device, $entry]))->assertNotFound();
    $this->get(route('client.cylinders.inspections.photos.show', [$this->device, $entry, $photo]))->assertNotFound();
    $client->companies()->attach($this->device->company_id);
    $this->get(route('client.cylinders.inspections.media', [$this->device, $entry]))->assertOk();
    $this->get(route('client.cylinders.inspections.photos.show', [$this->device, $entry, $photo]))->assertOk();
    $this->put(route('cylinders.inspections.update', [$this->device, $entry]), $this->entryData + ['revision' => 1])->assertForbidden();
    CompanySettings::first()->update(['enabled_modules' => ['client_zone']]);
    $this->get(route('client.cylinders.inspections.media', [$this->device, $entry]))->assertForbidden();
    auth()->logout();
    $this->get(route('cylinders.inspections.photos.show', [$this->device, $entry, $photo]))->assertRedirect(route('login'));
});

test('inspection measurements sort before pagination in both directions', function () {
    foreach (range(1, 21) as $i) {
        $this->device->inspections()->create(array_replace($this->entryData, ['weight_kg' => $i, 'working_pressure_bar' => $i, 'inspector_name' => 'Test']));
    }
    foreach (['weight', 'pressure'] as $key) {
        foreach (['asc' => 1, 'desc' => 21] as $direction => $expected) {
            $response = $this->get(route('cylinders.show', [$this->device, 'table_sort' => $key, 'table_direction' => $direction]))->assertOk();
            expect((float) $response->viewData('inspections')->first()->weight_kg)->toBe((float) $expected);
        }
    }
});

test('inspection media migration can resume and preserves existing files and foreign keys', function () {
    $this->post(route('cylinders.photo.store', $this->device), ['photo' => UploadedFile::fake()->image('device.jpg')])->assertSessionHasNoErrors();
    $generalPhoto = $this->device->fresh()->photo;
    $migration = require database_path('migrations/2026_10_10_000001_extend_cylinder_inspection_media.php');
    $migration->up();
    $migration->up();
    $this->post(route('cylinders.inspections.store', $this->device), $this->entryData + ['inspection_photos' => [UploadedFile::fake()->image('inspection.jpg')]])->assertSessionHasNoErrors();
    expect($this->device->fresh()->photo->id)->toBe($generalPhoto->id)
        ->and($this->device->inspections()->sole()->photos()->count())->toBe(1);
    Storage::disk('local')->assertExists($generalPhoto->stored_path);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
});
