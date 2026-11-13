<?php

use App\Models\CompanySettings;
use App\Models\ExpenseInvoice;
use App\Models\User;
use App\Services\UserPermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    CompanySettings::create(['name' => 'Firma testowa', 'enabled_modules' => ['expenses']]);
    Http::preventStrayRequests();
});

function expensePhotoUser(): User
{
    $user = User::factory()->create();
    $role = Role::findOrCreate('expense_photo_employee');
    $role->givePermissionTo(Permission::findOrCreate('expenses.use'));
    $user->assignRole($role);
    app(UserPermissions::class)->initialize($user);

    return $user;
}

test('photo payment proposals require evidence and cannot settle an invoice', function ($status, $evidence, $expected) {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'end_turn', 'content' => [['text' => json_encode(['payment_status' => $status, 'payment_evidence' => $evidence])]]])]);
    $response = $this->actingAs(expensePhotoUser())->postJson(route('expenses.scan'), ['photo' => UploadedFile::fake()->image('invoice.jpg')]);
    if ($status === 'settled') {
        $response->assertUnprocessable();
    } else {
        $response->assertOk()->assertJsonPath('proposal.payment_status', $expected);
    }
    expect(ExpenseInvoice::count())->toBe(0);
})->with([['paid', 'Zapłacono 123 zł', 'paid'], ['partial', 'Zapłacono 50 zł, pozostało 73 zł', 'partial'], ['unpaid', 'Nie zapłacono', 'unpaid'], ['paid', null, null], [null, null, null], ['settled', 'Zapłacono', null]]);

test('purchase entry opens the manual wizard without photo controls', function () {
    $this->actingAs(expensePhotoUser())->get(route('expenses.create'))->assertOk()
        ->assertSee('data-entry-wizard="1"', false)
        ->assertDontSee('Zrób zdjęcie')->assertDontSee('Wybierz z galerii')->assertDontSee('Wybierz zdjęcie z dysku')
        ->assertDontSee('type="file"', false)->assertDontSee('expense-scan.js');
});
