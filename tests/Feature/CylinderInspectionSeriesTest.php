<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\CylinderInspection;
use App\Models\CylinderInspectionSeries;
use App\Models\User;
use App\Services\CylinderPhotoRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    CompanySettings::create(['name' => 'Serie', 'enabled_modules' => ['cylinders', 'client_zone']]);
    $this->operator = User::factory()->create();
    $this->operator->assignRole(Role::findOrCreate('superadmin'));
    $this->company = Company::create(['name' => 'Klient bez NIP', 'company_type' => 'client', 'status' => 'pending']);
    $this->devices = collect(range(1, 2))->map(fn ($i) => Cylinder::create([
        'company_id' => $this->company->id, 'name' => 'Butla '.$i, 'type' => 'Butla', 'manufacturer_mark' => 'M', 'serial_number' => 'SER-'.$i,
    ]));
    $this->seriesData = ['name' => 'Seria testowa', 'company_id' => $this->company->id, 'responsible_id' => $this->operator->id, 'cylinder_ids' => $this->devices->pluck('id')->all()];
    $this->inspectionData = ['inspected_at' => today()->format('Y-m-d'), 'result' => 'no_findings', 'observations' => 'Sprawdzono', 'weight_kg' => '45,125'];
    $this->actingAs($this->operator);
});

test('series saves a draft resumes progress creates history and explicitly completes', function () {
    $previous = $this->devices->first()->inspections()->create(['inspected_at' => '2025-01-01', 'result' => 'no_findings', 'observations' => 'Poprzedni', 'inspector_name' => 'Inspektor']);
    $this->get(route('cylinders.series.create', ['cylinder_ids' => $this->devices->pluck('id')->all()]))->assertOk()->assertSee('Klient bez NIP');
    $this->post(route('cylinders.series.store'), $this->seriesData)->assertSessionHasNoErrors()->assertRedirect();
    $series = CylinderInspectionSeries::sole();
    expect($series->status)->toBe('draft')->and($series->items()->count())->toBe(2);
    $this->get(route('cylinders.series.index'))->assertOk()->assertSee('Seria testowa');
    $items = $series->items()->orderBy('id')->get();
    $this->get(route('cylinders.series.inspect', [$series, $items[0]]))->assertOk()->assertSee('SER-1');
    $this->patch(route('cylinders.series.complete', $series))->assertSessionHasErrors('series');
    $this->post(route('cylinders.series.inspections.store', [$series, $items[0]]), $this->inspectionData)->assertRedirect(route('cylinders.series.inspect', [$series, $items[1]]));
    expect($series->fresh()->status)->toBe('in_progress')->and($items[0]->fresh()->inspection->inspector_id)->toBe($this->operator->id);
    $this->get(route('cylinders.series.show', $series))->assertOk()->assertSee('Postęp: 1 / 2')->assertSee('Kontynuuj przeglądy');
    expect($previous->fresh()->observations)->toBe('Poprzedni')->and($this->devices->first()->latestInspection->id)->toBe($items[0]->fresh()->inspection_id);
    $this->post(route('cylinders.series.inspections.store', [$series, $items[1]]), $this->inspectionData)->assertRedirect(route('cylinders.series.show', $series));
    expect($series->fresh()->status)->toBe('in_progress');
    $this->patch(route('cylinders.series.complete', $series))->assertRedirect();
    expect($series->fresh()->status)->toBe('completed')->and($series->fresh()->completed_at)->not->toBeNull();
    $this->get(route('cylinders.series.show', $series))->assertOk()->assertSee('Zakończona');
    $this->post(route('cylinders.series.inspections.store', [$series, $items[1]]), $this->inspectionData)->assertStatus(409);
    expect(CylinderInspection::count())->toBe(3);
});

test('series accepts unassigned devices but validates client staff duplicate and archived selections', function () {
    $unassigned = Cylinder::create(['name' => 'Bez klienta', 'type' => 'Butla', 'manufacturer_mark' => 'M', 'serial_number' => 'FREE']);
    $this->post(route('cylinders.series.store'), array_replace($this->seriesData, ['company_id' => null, 'cylinder_ids' => [$unassigned->id]]))->assertSessionHasNoErrors();
    expect(CylinderInspectionSeries::sole()->company_id)->toBeNull()->and($unassigned->fresh()->company_id)->toBeNull();
    $this->post(route('cylinders.series.store'), array_replace($this->seriesData, ['cylinder_ids' => [$unassigned->id]]))->assertSessionHasErrors('company_id');
    $this->post(route('cylinders.series.store'), array_replace($this->seriesData, ['cylinder_ids' => [999999]]))->assertSessionHasErrors('cylinder_ids');
    $this->post(route('cylinders.series.store'), array_replace($this->seriesData, ['cylinder_ids' => [$unassigned->id, $unassigned->id]]))->assertSessionHasErrors('cylinder_ids.0');
    $this->post(route('cylinders.series.store'), array_replace($this->seriesData, ['cylinder_ids' => []]))->assertSessionHasErrors('cylinder_ids');
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $this->post(route('cylinders.series.store'), array_replace($this->seriesData, ['responsible_id' => $client->id]))->assertSessionHasErrors('responsible_id');
    $this->devices->first()->update(['archived_at' => now()]);
    $this->post(route('cylinders.series.store'), $this->seriesData)->assertSessionHasErrors('cylinder_ids');
    expect(CylinderInspectionSeries::count())->toBe(1);
});

test('series prevents duplicate saves mismatched items and archived inspection writes', function () {
    $this->post(route('cylinders.series.store'), $this->seriesData)->assertSessionHasNoErrors();
    $series = CylinderInspectionSeries::sole();
    $items = $series->items()->orderBy('id')->get();
    $other = CylinderInspectionSeries::create(['name' => 'Inna', 'responsible_id' => $this->operator->id, 'created_by' => $this->operator->id]);
    $this->get(route('cylinders.series.inspect', [$other, $items[0]]))->assertNotFound();
    $this->post(route('cylinders.series.inspections.store', [$other, $items[0]]), $this->inspectionData)->assertNotFound();
    $this->post(route('cylinders.series.inspections.store', [$series, $items[0]]), $this->inspectionData)->assertRedirect();
    $this->post(route('cylinders.series.inspections.store', [$series, $items[0]]), $this->inspectionData)->assertStatus(409);
    $this->devices[1]->update(['archived_at' => now()]);
    $this->post(route('cylinders.series.inspections.store', [$series, $items[1]]), $this->inspectionData)->assertStatus(409);
    expect(CylinderInspection::count())->toBe(1)->and($items[1]->fresh()->inspection_id)->toBeNull();
});

test('series inspection validates input and saves private media through existing pipeline', function () {
    Storage::fake('local');
    $this->post(route('cylinders.series.store'), $this->seriesData)->assertSessionHasNoErrors();
    $series = CylinderInspectionSeries::sole();
    $item = $series->items()->first();
    $this->post(route('cylinders.series.inspections.store', [$series, $item]), array_replace($this->inspectionData, ['result' => 'invalid']))->assertSessionHasErrors('result');
    expect($series->fresh()->status)->toBe('draft')->and($item->fresh()->inspection_id)->toBeNull();
    $this->post(route('cylinders.series.inspections.store', [$series, $item]), $this->inspectionData + ['inspection_video' => UploadedFile::fake()->create('film.mp4', 1, 'video/mp4'), 'inspection_photos' => [UploadedFile::fake()->image('photo.jpg')]])->assertSessionHasNoErrors();
    $entry = $item->fresh()->inspection;
    expect((float) $entry->weight_kg)->toBe(45.125)->and($entry->photos()->count())->toBe(1)->and($entry->videos()->count())->toBe(1);
    Storage::disk('local')->assertExists($entry->photos()->first()->stored_path);
});

test('failed media processing rolls back inspection series progress and uploaded files', function () {
    Storage::fake('local');
    $this->post(route('cylinders.series.store'), $this->seriesData)->assertSessionHasNoErrors();
    $series = CylinderInspectionSeries::sole();
    $item = $series->items()->first();
    $this->mock(CylinderPhotoRenderer::class, function ($mock) {
        $mock->shouldReceive('render')->once()->andThrow(ValidationException::withMessages(['photo' => 'Błąd przetwarzania']));
    });
    $this->post(route('cylinders.series.inspections.store', [$series, $item]), $this->inspectionData + [
        'inspection_video' => UploadedFile::fake()->create('film.mp4', 1, 'video/mp4'),
        'inspection_photos' => [UploadedFile::fake()->image('photo.jpg')],
    ])->assertSessionHasErrors('inspection_photos.0');
    expect(CylinderInspection::count())->toBe(0)->and($item->fresh()->inspection_id)->toBeNull()
        ->and($series->fresh()->status)->toBe('draft')->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('series endpoints require module write permission and deny clients and staff preview', function () {
    $this->post(route('cylinders.series.store'), $this->seriesData)->assertSessionHasNoErrors();
    $series = CylinderInspectionSeries::sole();
    $item = $series->items()->first();
    $reader = User::factory()->create();
    $role = Role::findOrCreate('series_reader');
    $role->givePermissionTo('cylinders.view');
    $reader->assignRole($role);
    $writer = User::factory()->create();
    $writerRole = Role::findOrCreate('series_writer');
    $writerRole->givePermissionTo(['cylinders.view', 'cylinders.manage']);
    $writer->assignRole($writerRole);
    $this->actingAs($writer)->get(route('cylinders.series.show', $series))->assertOk();
    $this->post(route('cylinders.series.inspections.store', [$series, $item]), $this->inspectionData)->assertRedirect();
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->companies()->attach($this->company);
    foreach ([$reader, $client] as $user) {
        $this->actingAs($user);
        foreach (['cylinders.series.index' => [], 'cylinders.series.create' => ['cylinder_ids' => $this->devices->pluck('id')->all()], 'cylinders.series.show' => [$series], 'cylinders.series.inspect' => [$series, $item]] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertForbidden();
        }
        $this->post(route('cylinders.series.store'), $this->seriesData)->assertForbidden();
        $this->post(route('cylinders.series.inspections.store', [$series, $item]), $this->inspectionData)->assertForbidden();
        $this->patch(route('cylinders.series.complete', $series))->assertForbidden();
    }
    $this->actingAs($this->operator)->withSession(['client_zone_company_id' => $this->company->id])->get(route('client-zone.cylinders.index'))->assertOk()->assertDontSee('Utwórz serię przeglądów');
    CompanySettings::first()->update(['enabled_modules' => []]);
    $this->get(route('cylinders.series.index'))->assertForbidden();
    expect(CylinderInspection::count())->toBe(1);
});

test('series tables sort allowlisted columns in both directions before pagination and preserve filters', function () {
    foreach (range(1, 31) as $i) {
        $series = CylinderInspectionSeries::create(['name' => sprintf('Seria %02d', $i), 'company_id' => $this->company->id, 'responsible_id' => $this->operator->id, 'created_by' => $this->operator->id]);
        $series->items()->create(['cylinder_id' => $this->devices->first()->id]);
    }
    foreach (['name', 'company', 'responsible', 'status', 'saved', 'total', 'created'] as $column) {
        foreach (['asc', 'desc'] as $direction) {
            $response = $this->get(route('cylinders.series.index', ['table_sort' => $column, 'table_direction' => $direction, 'status' => 'draft']))->assertOk();
            expect($response->viewData('seriesList')->count())->toBe(30)->and($response->viewData('seriesList')->total())->toBe(31);
            if ($column === 'name') {
                expect($response->viewData('seriesList')->first()->name)->toBe($direction === 'asc' ? 'Seria 01' : 'Seria 31');
            }
        }
    }
    $series = CylinderInspectionSeries::first();
    foreach (range(3, 31) as $i) {
        $device = Cylinder::create(['name' => sprintf('Butla %02d', $i), 'type' => 'Butla', 'manufacturer_mark' => sprintf('M%02d', $i), 'serial_number' => sprintf('SN%02d', $i)]);
        $series->items()->create(['cylinder_id' => $device->id]);
    }
    $series->items()->create(['cylinder_id' => $this->devices[1]->id]);
    foreach (['name', 'mark', 'serial', 'status', 'date'] as $column) {
        foreach (['asc', 'desc'] as $direction) {
            $response = $this->get(route('cylinders.series.show', [$series, 'table_sort' => $column, 'table_direction' => $direction]))->assertOk();
            expect($response->viewData('items')->count())->toBe(30)->and($response->viewData('items')->total())->toBe(31);
        }
    }
    $this->get(route('cylinders.series.index', ['table_sort' => 'invalid SQL', 'q' => 'Seria 12']))->assertOk()->assertSee('Seria 12')->assertDontSee('Seria 13');
});

test('series sorting orders client responsible progress status and dates by their stored values', function () {
    $seriesIds = [];
    foreach ([['A', 'draft', 0, '2026-01-01'], ['B', 'in_progress', 1, '2026-02-01'], ['C', 'completed', 2, '2026-03-01']] as [$letter, $status, $saved, $date]) {
        $company = Company::create(['name' => $letter, 'company_type' => 'client']);
        $user = User::factory()->create(['name' => $letter]);
        $series = CylinderInspectionSeries::create(['name' => $letter, 'company_id' => $company->id, 'responsible_id' => $user->id, 'created_by' => $this->operator->id, 'status' => $status]);
        $series->forceFill(['created_at' => $date.' 12:00:00'])->save();
        foreach (range(0, $saved) as $i) {
            $device = Cylinder::create(['name' => $letter.$i, 'type' => 'Butla', 'serial_number' => $letter.$i, 'manufacturer_mark' => $letter]);
            $inspection = $i < $saved ? $device->inspections()->create(['inspected_at' => $date, 'result' => 'no_findings', 'observations' => 'Test', 'inspector_name' => 'Test']) : null;
            $series->items()->create(['cylinder_id' => $device->id, 'inspection_id' => $inspection?->id]);
        }
        $seriesIds[] = $series->id;
    }
    foreach (['name', 'company', 'responsible', 'saved', 'total', 'created', 'status'] as $column) {
        $expected = $column === 'status' ? [$seriesIds[2], $seriesIds[0], $seriesIds[1]] : $seriesIds;
        foreach (['asc', 'desc'] as $direction) {
            $response = $this->get(route('cylinders.series.index', ['table_sort' => $column, 'table_direction' => $direction]))->assertOk();
            expect($response->viewData('seriesList')->pluck('id')->all())->toBe($direction === 'asc' ? $expected : array_reverse($expected));
        }
    }
});

test('series migration resumes partial ddl without losing existing draft series', function () {
    $this->post(route('cylinders.series.store'), $this->seriesData)->assertSessionHasNoErrors();
    $series = CylinderInspectionSeries::sole();
    Schema::drop('cylinder_inspection_series_items');
    $migration = require database_path('migrations/2026_10_14_000001_create_cylinder_inspection_series.php');
    $migration->up();
    $series->items()->create(['cylinder_id' => $this->devices->first()->id]);
    $migration->up();
    expect($series->fresh()->name)->toBe('Seria testowa')->and($series->items()->count())->toBe(1);
});
