<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\CylinderInspectionSeries;
use App\Models\CylinderProtocol;
use App\Models\User;
use App\Services\CylinderProtocolService;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    CompanySettings::create(['name' => 'TPED Test', 'enabled_modules' => ['cylinders'], 'primary_color' => '#1A4D3A']);
    $this->operator = User::factory()->create();
    $this->operator->assignRole(Role::findOrCreate('superadmin'));
    $this->client = Company::create(['name' => 'Klient źródłowy', 'company_type' => 'client']);
    $this->device = Cylinder::create(['name' => 'Butla azotowa', 'type' => 'Butla', 'manufacturer_mark' => 'ACME', 'serial_number' => 'PDF-001', 'company_id' => $this->client->id]);
    $this->entry = $this->device->inspections()->create(['inspected_at' => '2026-01-02', 'result' => 'no_findings', 'observations' => 'Pierwotna ocena', 'inspector_name' => 'Jan Inspektor']);
    $this->series = CylinderInspectionSeries::create(['name' => 'Seria PDF', 'company_id' => $this->client->id, 'responsible_id' => $this->operator->id, 'created_by' => $this->operator->id, 'status' => 'completed', 'completed_at' => now()]);
    $this->series->items()->create(['cylinder_id' => $this->device->id, 'inspection_id' => $this->entry->id]);
    $this->service = app(CylinderProtocolService::class);
    $this->fingerprint = fn () => $this->service->fingerprint($this->service->snapshot($this->series));
    $this->actingAs($this->operator);
});

test('protocol preview renders actual draft PDF and issue freezes source data and bytes', function () {
    $fingerprint = ($this->fingerprint)();
    $this->get(route('cylinders.series.protocol.preview', $this->series))->assertOk()->assertSee('Wydaj i zapisz protokół PDF');
    $draft = $this->get(route('cylinders.series.protocol.draft', [$this->series, 'fingerprint' => $fingerprint]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(str_starts_with($draft->getContent(), '%PDF-'))->toBeTrue();
    $this->post(route('cylinders.series.protocol.issue', $this->series), ['fingerprint' => $fingerprint])->assertRedirect()->assertSessionHasNoErrors();
    $protocol = CylinderProtocol::sole();
    $bytes = Storage::disk('local')->get($protocol->stored_path);
    expect($protocol->snapshot['items'][0]['inspection']['observations'])->toBe('Pierwotna ocena')
        ->and($protocol->snapshot['client']['name'])->toBe('Klient źródłowy')->and($protocol->sha256)->toBe(hash('sha256', $bytes));
    $this->device->update(['name' => 'Nowa nazwa']);
    $this->entry->update(['observations' => 'Zmieniona ocena']);
    $this->client->update(['name' => 'Nowy klient']);
    CompanySettings::first()->update(['name' => 'Nowy wystawca', 'primary_color' => '#123456']);
    $this->get(route('cylinders.series.protocol.download', $this->series))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect(Storage::disk('local')->get($protocol->stored_path))->toBe($bytes)
        ->and($protocol->fresh()->snapshot['issuer']['name'])->toBe('TPED Test');
    $this->get(route('cylinders.series.protocol.preview', $this->series))->assertRedirect(route('cylinders.series.protocol.download', $this->series));
    $this->post(route('cylinders.series.protocol.issue', $this->series), ['fingerprint' => $fingerprint])->assertRedirect();
    expect(CylinderProtocol::count())->toBe(1)->and(Storage::disk('local')->allFiles())->toHaveCount(1);
    expect(fn () => $protocol->update(['number' => 'FORGED']))->toThrow(LogicException::class);
    expect(fn () => $protocol->delete())->toThrow(LogicException::class);
});

test('protocol issue rejects unfinished empty and changed data without creating files', function () {
    $this->series->update(['status' => 'in_progress']);
    $this->post(route('cylinders.series.protocol.issue', $this->series), ['fingerprint' => ($this->fingerprint)()])->assertSessionHasErrors('protocol');
    $this->series->update(['status' => 'completed']);
    $fingerprint = ($this->fingerprint)();
    $this->entry->update(['observations' => 'Zmieniono po podglądzie']);
    $this->post(route('cylinders.series.protocol.issue', $this->series), ['fingerprint' => $fingerprint])->assertSessionHasErrors('protocol');
    $this->get(route('cylinders.series.protocol.draft', [$this->series, 'fingerprint' => $fingerprint]))->assertStatus(409);
    $this->series->items()->first()->update(['inspection_id' => null]);
    $this->post(route('cylinders.series.protocol.issue', $this->series), ['fingerprint' => ($this->fingerprint)()])->assertSessionHasErrors('protocol');
    $this->series->items()->delete();
    $this->post(route('cylinders.series.protocol.issue', $this->series), ['fingerprint' => ($this->fingerprint)()])->assertSessionHasErrors('protocol');
    expect(CylinderProtocol::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('protocol download requires existing intact private file without regeneration', function () {
    $this->get(route('cylinders.series.protocol.download', $this->series))->assertNotFound();
    $protocol = $this->service->issue($this->series, $this->operator, ($this->fingerprint)());
    Storage::disk('local')->put($protocol->stored_path, 'modified');
    $this->get(route('cylinders.series.protocol.download', $this->series))->assertStatus(409);
    Storage::disk('local')->delete($protocol->stored_path);
    $this->get(route('cylinders.series.protocol.download', $this->series))->assertNotFound();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('protocol access follows module and write permissions and never exposes other series to clients', function () {
    $fingerprint = ($this->fingerprint)();
    $this->post(route('cylinders.series.protocol.issue', $this->series), ['fingerprint' => $fingerprint])->assertSessionHasNoErrors();
    $reader = User::factory()->create();
    $role = Role::findOrCreate('protocol_reader');
    $role->givePermissionTo('cylinders.view');
    $reader->assignRole($role);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->companies()->attach($this->client);
    foreach ([$reader, $client] as $user) {
        $this->actingAs($user);
        foreach (['preview', 'download', 'draft'] as $action) {
            $this->get(route('cylinders.series.protocol.'.$action, [$this->series, 'fingerprint' => $fingerprint]))->assertForbidden();
        }
        $this->post(route('cylinders.series.protocol.issue', $this->series), ['fingerprint' => $fingerprint])->assertForbidden();
    }
    $this->actingAs($this->operator);
    $other = CylinderInspectionSeries::create(['name' => 'Inna', 'responsible_id' => $this->operator->id, 'created_by' => $this->operator->id]);
    $this->get(route('cylinders.series.protocol.download', $other))->assertNotFound();
    CompanySettings::first()->update(['enabled_modules' => []]);
    $this->get(route('cylinders.series.protocol.download', $this->series))->assertForbidden();
    auth()->logout();
    $this->get(route('cylinders.series.protocol.download', $this->series))->assertRedirect(route('login'));
});

test('protocol failure removes private output and leaves no issued record', function () {
    CylinderProtocol::creating(function () {
        throw new RuntimeException('Database failure after file write');
    });
    expect(fn () => $this->service->issue($this->series, $this->operator, ($this->fingerprint)()))->toThrow(RuntimeException::class);
    expect(CylinderProtocol::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});
