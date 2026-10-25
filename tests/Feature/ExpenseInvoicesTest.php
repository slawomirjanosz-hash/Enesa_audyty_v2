<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\ExpenseInvoice;
use App\Models\User;
use App\Services\UserPermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function expenseUser(bool $accounting = false): User
{
    $user = User::factory()->create();
    $role = Role::findOrCreate('expense_employee');
    $role->givePermissionTo(Permission::findOrCreate('expenses.use'));
    $user->assignRole($role);
    if ($accounting) {
        $user->givePermissionTo(Permission::findOrCreate('expenses.accounting'));
    }
    app(UserPermissions::class)->initialize($user);

    return $user;
}

function expenseData(array $changes = []): array
{
    return $changes + ['purchase_date' => '2026-10-23', 'invoice_number' => 'FV/23/2026', 'supplier_name' => 'Sklep testowy', 'payment_method' => 'unknown', 'payment_source' => 'unknown', 'payment_status' => 'unknown'];
}

function expenseRecord(User $owner, array $changes = []): ExpenseInvoice
{
    return ExpenseInvoice::create(expenseData($changes) + ['owner_id' => $owner->id, 'owner_name' => $owner->name, 'created_by' => $owner->id]);
}

beforeEach(function () {
    CompanySettings::create(['name' => 'PRINŻ test', 'enabled_modules' => ['expenses']]);
    Http::preventStrayRequests();
});

test('employees create only their own invoices and cannot forge ownership settlement or accounting data', function () {
    $user = expenseUser();
    $other = expenseUser();
    $this->actingAs($user)->post(route('expenses.store'), expenseData(['owner_id' => $other->id, 'created_by' => $other->id, 'is_unassigned' => true, 'settled_at' => now(), 'accounting_note' => 'forged']))->assertSessionHasNoErrors();
    $invoice = ExpenseInvoice::firstOrFail();
    expect($invoice->owner_id)->toBe($user->id)->and($invoice->created_by)->toBe($user->id)->and($invoice->is_unassigned)->toBeFalse()->and($invoice->settled_at)->toBeNull()->and($invoice->accounting_note)->toBeNull();
    $this->assertDatabaseMissing('activity_logs', ['auditable_type' => ExpenseInvoice::class]);
    $foreign = expenseRecord($other, ['invoice_number' => 'PRIVATE-OTHER']);
    $this->get(route('expenses.index'))->assertOk()->assertSee('FV/23/2026')->assertDontSee('PRIVATE-OTHER');
    $this->get(route('expenses.edit', $foreign))->assertNotFound();
    $this->put(route('expenses.update', $foreign), expenseData(['revision' => 1]))->assertForbidden();
    $this->get(route('expenses.index', ['tab' => 'all']))->assertForbidden();
});

test('accounting publishes only chosen invoices and claiming atomically hides them from colleagues', function () {
    $finance = expenseUser(true);
    $first = expenseUser();
    $second = expenseUser();
    $this->actingAs($finance)->post(route('expenses.store'), expenseData(['owner_id' => null, 'accounting_note' => 'Poufne księgowość']))->assertSessionHasNoErrors();
    $invoice = ExpenseInvoice::firstOrFail();
    expect($invoice->is_unassigned)->toBeTrue()->and($invoice->owner_id)->toBeNull();
    foreach ([$first, $second] as $employee) {
        $this->actingAs($employee)->get(route('expenses.index', ['tab' => 'pool']))->assertOk()->assertSee('FV/23/2026');
        $this->get(route('expenses.edit', $invoice))->assertOk()->assertSee('To mój zakup')->assertDontSee('Poufne księgowość');
        $this->put(route('expenses.update', $invoice), expenseData(['revision' => 1]))->assertForbidden();
    }
    $this->actingAs($first)->post(route('expenses.claim', $invoice), ['confirm' => 1, 'owner_id' => $second->id])->assertSessionHas('success');
    expect($invoice->fresh()->owner_id)->toBe($first->id)->and($invoice->fresh()->is_unassigned)->toBeFalse();
    $this->actingAs($second)->post(route('expenses.claim', $invoice), ['confirm' => 1])->assertStatus(409);
    $this->get(route('expenses.edit', $invoice))->assertNotFound();
    $this->get(route('expenses.index', ['tab' => 'pool']))->assertDontSee('FV/23/2026');
    $this->actingAs($finance)->get(route('expenses.index', ['tab' => 'all']))->assertSee('FV/23/2026');
});

test('claim confirmation and stale edit protection prevent accidental ownership or data overwrite', function () {
    $user = expenseUser();
    $invoice = expenseRecord($user);
    $this->actingAs($user)->post(route('expenses.claim', $invoice))->assertSessionHasErrors('confirm');
    $this->put(route('expenses.update', $invoice), expenseData(['revision' => 1, 'description' => 'Pierwsza zmiana']))->assertSessionHasNoErrors();
    $this->put(route('expenses.update', $invoice), expenseData(['revision' => 1, 'description' => 'Stary formularz']))->assertStatus(409);
    expect($invoice->fresh()->description)->toBe('Pierwsza zmiana');
});

test('only accounting can settle complete assigned invoices and settled data is immutable', function () {
    $user = expenseUser();
    $finance = expenseUser(true);
    $invoice = expenseRecord($user);
    $this->actingAs($user)->post(route('expenses.settle', $invoice), ['confirm' => 1, 'revision' => 1])->assertForbidden();
    $this->actingAs($finance)->post(route('expenses.settle', $invoice), ['confirm' => 1, 'revision' => 1])->assertStatus(422);
    $invoice->update(['gross_amount' => 100, 'payment_method' => 'card', 'payment_source' => 'personal', 'payment_status' => 'paid', 'paid_by' => $user->name]);
    $this->post(route('expenses.settle', $invoice), ['confirm' => 1, 'revision' => 1])->assertSessionHas('success');
    expect($invoice->fresh()->settled_by)->toBe($finance->id);
    $this->put(route('expenses.update', $invoice), expenseData(['revision' => 2, 'gross_amount' => 200]))->assertForbidden();
    $this->actingAs($user)->get(route('expenses.edit', $invoice))->assertOk()->assertSee('zamknięta do edycji');
});

test('deleted owners do not publish previously private invoices to the shared pool', function () {
    $owner = expenseUser();
    $other = expenseUser();
    $invoice = expenseRecord($owner, ['invoice_number' => 'DELETED-PRIVATE']);
    $owner->forceDelete();
    expect($invoice->fresh()->owner_id)->toBeNull()->and($invoice->fresh()->is_unassigned)->toBeFalse();
    $this->actingAs($other)->get(route('expenses.index', ['tab' => 'pool']))->assertDontSee('DELETED-PRIVATE');
    $this->get(route('expenses.edit', $invoice))->assertNotFound();
});

test('server sorting filters and pagination preserve own and shared access boundaries', function () {
    $user = expenseUser();
    $other = expenseUser();
    for ($i = 1; $i <= 35; $i++) {
        expenseRecord($user, ['invoice_number' => sprintf('OWN-%02d', $i), 'gross_amount' => $i]);
    }
    expenseRecord($other, ['invoice_number' => 'PRIVATE-SECRET', 'gross_amount' => 0]);
    $response = $this->actingAs($user)->get(route('expenses.index', ['table_sort' => 'gross_amount', 'table_direction' => 'asc', 'month' => '2026-10']));
    $response->assertOk()->assertSeeInOrder(['OWN-01', 'OWN-02', 'OWN-30'])->assertDontSee('OWN-31')->assertDontSee('PRIVATE-SECRET')->assertSee('data-server-sort', false);
    $this->get(route('expenses.index', ['table_sort' => 'gross_amount', 'table_direction' => 'desc']))->assertSeeInOrder(['OWN-35', 'OWN-34', 'OWN-33']);
    $this->get(route('expenses.index', ['table_sort' => 'gross_amount desc; drop table users', 'table_direction' => 'bogus']))->assertOk();
    $this->get(route('expenses.index', ['month' => '2026-09']))->assertDontSee('OWN-01');
});

test('disabled modules and client accounts cannot access records or OCR', function () {
    $user = expenseUser();
    CompanySettings::first()->update(['enabled_modules' => []]);
    $this->actingAs($user)->get(route('expenses.index'))->assertForbidden();
    CompanySettings::first()->update(['enabled_modules' => ['expenses']]);
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $client->givePermissionTo('expenses.use');
    $this->actingAs($client)->get(route('expenses.index'))->assertForbidden();
    $this->postJson(route('expenses.scan'))->assertForbidden();
    Http::assertNothingSent();
});

test('photo extraction only returns reviewed fields deletes the upload and never creates an invoice', function () {
    Storage::fake('local');
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'end_turn', 'content' => [['text' => json_encode(['invoice_number' => 'FV/PHOTO', 'wz_number' => 'WZ/123', 'purchase_date' => '2026-10-22', 'supplier_name' => 'Odczytany dostawca', 'supplier_nip' => '1234567890', 'notes' => 'Sprawdź datę', 'owner_id' => 999, 'payment_status' => 'paid'])]]])]);
    $photo = UploadedFile::fake()->image('wz.jpg');
    $path = $photo->getRealPath();
    $this->actingAs(expenseUser())->postJson(route('expenses.scan'), ['photo' => $photo])->assertOk()->assertJsonPath('proposal.invoice_number', 'FV/PHOTO')->assertJsonMissingPath('proposal.owner_id')->assertJsonMissingPath('proposal.payment_status');
    expect(is_file($path))->toBeFalse();
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
    $this->assertDatabaseCount('expense_invoices', 0);
    Http::assertSentCount(1);
    expect(DB::table('expense_scan_reads')->first()->result)->not->toContain('base64', 'test-key');
});

test('OCR failure and missing configuration preserve manual entry and never leak provider contents', function () {
    $user = expenseUser();
    config(['services.anthropic.key' => null]);
    $this->actingAs($user)->postJson(route('expenses.scan'), ['photo' => UploadedFile::fake()->image('wz.jpg')])->assertStatus(422);
    Http::assertNothingSent();
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'secret provider data'], 500)]);
    $photo = UploadedFile::fake()->image('failed.png');
    $path = $photo->getRealPath();
    $this->postJson(route('expenses.scan'), ['photo' => $photo])->assertStatus(422)->assertDontSee('secret provider data');
    expect(is_file($path))->toBeFalse();
    $this->post(route('expenses.store'), expenseData())->assertSessionHasNoErrors();
});

test('amount validation never invents net values or accepts net greater than gross', function () {
    $this->actingAs(expenseUser())->post(route('expenses.store'), expenseData(['gross_amount' => '123.45', 'net_amount' => null]))->assertSessionHasNoErrors();
    expect(ExpenseInvoice::firstOrFail()->net_amount)->toBeNull();
    $this->post(route('expenses.store'), expenseData(['gross_amount' => '100', 'net_amount' => '200']))->assertSessionHasErrors('gross_amount');
});

test('every register data column accepts both sorting directions without widening access', function () {
    $owner = expenseUser();
    $private = expenseRecord(expenseUser(), ['invoice_number' => 'HIDDEN-SORT']);
    expenseRecord($owner, ['invoice_number' => 'OWN-SORT']);
    $this->actingAs($owner);
    foreach (['id', 'purchase_date', 'invoice_number', 'wz_number', 'ksef_number', 'supplier_name', 'net_amount', 'gross_amount', 'owner_name', 'payment_method', 'payment_source', 'payment_status', 'paid_by', 'cost_center', 'description', 'status'] as $column) {
        foreach (['asc', 'desc'] as $direction) {
            $this->get(route('expenses.index', ['table_sort' => $column, 'table_direction' => $direction]))->assertOk()->assertSee('OWN-SORT')->assertDontSee($private->invoice_number);
        }
    }
});

test('repeated identical photos reuse the extraction and daily budget blocks new paid analysis', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'end_turn', 'content' => [['text' => '{"invoice_number":"FV/CACHED"}']]])]);
    $user = expenseUser();
    $photo = UploadedFile::fake()->image('first.jpg');
    $bytes = file_get_contents($photo->getRealPath());
    $this->actingAs($user)->postJson(route('expenses.scan'), ['photo' => $photo])->assertOk();
    $copy = UploadedFile::fake()->createWithContent('copy.jpg', $bytes);
    $this->postJson(route('expenses.scan'), ['photo' => $copy])->assertOk()->assertJsonPath('proposal.invoice_number', 'FV/CACHED');
    Http::assertSentCount(1);
    for ($i = 0; $i < 9; $i++) {
        DB::table('expense_scan_reads')->insert(['user_id' => $user->id, 'fingerprint' => hash('sha256', 'budget-'.$i), 'created_at' => now()]);
    }
    $this->postJson(route('expenses.scan'), ['photo' => UploadedFile::fake()->image('new.jpg', 2, 2)])->assertStatus(422);
    Http::assertSentCount(1);
});

test('manual buyers remain private to accounting and are preserved during editing', function () {
    $finance = expenseUser(true);
    $employee = expenseUser();
    $this->actingAs($finance)->post(route('expenses.store'), expenseData(['owner_mode' => 'manual', 'owner_name' => 'Kupujący spoza bazy']))->assertSessionHasNoErrors();
    $invoice = ExpenseInvoice::firstOrFail();
    expect($invoice->owner_id)->toBeNull()->and($invoice->owner_name)->toBe('Kupujący spoza bazy')->and($invoice->is_unassigned)->toBeFalse();
    $this->get(route('expenses.edit', $invoice))->assertOk()->assertSee('Kupujący spoza bazy')->assertSee('Wpisz ręcznie');
    $this->put(route('expenses.update', $invoice), expenseData(['revision' => 1, 'owner_mode' => 'manual', 'owner_name' => 'Poprawiony kupujący']))->assertSessionHasNoErrors();
    expect($invoice->fresh()->owner_name)->toBe('Poprawiony kupujący');
    $this->actingAs($employee)->get(route('expenses.index', ['tab' => 'pool']))->assertDontSee('FV/23/2026');
    $this->get(route('expenses.edit', $invoice))->assertNotFound();
});

test('accounting closes invoices through payment status with inline validation and revision protection', function () {
    $finance = expenseUser(true);
    $invoice = expenseRecord($finance);
    $data = expenseData(['revision' => 1, 'owner_mode' => 'user', 'owner_id' => $finance->id, 'payment_status' => 'settled']);
    $this->actingAs($finance)->from(route('expenses.edit', $invoice))->put(route('expenses.update', $invoice), $data)
        ->assertRedirect(route('expenses.edit', $invoice))->assertSessionHasErrors(['gross_amount', 'payment_method', 'payment_source', 'paid_by']);
    expect($invoice->fresh()->settled_at)->toBeNull();
    $complete = array_replace($data, ['gross_amount' => '123.00', 'net_amount' => '100.00', 'vat_rate' => '23', 'payment_method' => 'card', 'payment_source' => 'company', 'paid_by' => $finance->name]);
    $this->put(route('expenses.update', $invoice), $complete)->assertSessionHasNoErrors();
    expect($invoice->fresh()->payment_status)->toBe('settled')->and($invoice->fresh()->settled_by)->toBe($finance->id)->and($invoice->fresh()->settled_at)->not->toBeNull();
    $this->put(route('expenses.update', $invoice), array_replace($complete, ['revision' => 2]))->assertForbidden();
    $employee = expenseUser();
    $own = expenseRecord($employee);
    $this->actingAs($employee)->put(route('expenses.update', $own), $complete)->assertForbidden();
    expect($own->fresh()->settled_at)->toBeNull();
});

test('dynamic filtering searches all accessible rows and returns only table results', function () {
    $owner = expenseUser();
    $other = expenseUser();
    expenseRecord($owner, ['invoice_number' => 'OWN-DYNAMIC', 'description' => 'Sprężarka unikalna']);
    expenseRecord($other, ['invoice_number' => 'PRIVATE-DYNAMIC', 'description' => 'Sprężarka unikalna']);
    $this->actingAs($owner)->withHeader('X-Expense-Results', '1')->get(route('expenses.index', ['search' => 'Sprężarka', 'table_sort' => 'gross_amount', 'table_direction' => 'asc']))
        ->assertOk()->assertSee('OWN-DYNAMIC')->assertDontSee('PRIVATE-DYNAMIC')->assertDontSee('<html', false)->assertSee('Edytuj');
});

test('supplier choices include only accessible active suppliers and supply the NIP', function () {
    $finance = expenseUser(true);
    Company::create(['name' => 'Dostawca do wyboru', 'company_type' => 'supplier', 'nip' => '1234567890']);
    Company::create(['name' => 'Zwykły klient ukryty', 'company_type' => 'client']);
    Company::create(['name' => 'Dostawca archiwalny', 'company_type' => 'supplier', 'archived_at' => now()]);
    $this->actingAs($finance)->get(route('expenses.create'))->assertOk()->assertSee('Dostawca do wyboru')->assertSee('data-nip="1234567890"', false)->assertDontSee('Zwykły klient ukryty')->assertDontSee('Dostawca archiwalny');
    $finance->assignRole(Role::findOrCreate('auditor'));
    $this->get(route('expenses.create'))->assertOk()->assertDontSee('Dostawca do wyboru');
});

test('payment sorting matches the displayed closed status for older settled invoices', function () {
    $owner = expenseUser();
    expenseRecord($owner, ['invoice_number' => 'SORT-PAID', 'payment_status' => 'paid']);
    expenseRecord($owner, ['invoice_number' => 'SORT-CLOSED', 'payment_status' => 'paid', 'settled_at' => now()]);
    $this->actingAs($owner)->get(route('expenses.index', ['table_sort' => 'payment_status', 'table_direction' => 'asc']))
        ->assertOk()->assertSeeInOrder(['SORT-PAID', 'SORT-CLOSED']);
    $this->get(route('expenses.index', ['table_sort' => 'payment_status', 'table_direction' => 'desc']))
        ->assertOk()->assertSeeInOrder(['SORT-CLOSED', 'SORT-PAID']);
});
