<?php

use App\Models\CompanySettings;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['services.google.maps_key' => 'secret-test-key']);
    Http::preventStrayRequests();
    $this->routeEmployee = User::factory()->create();
    $role = Role::findOrCreate('route_employee');
    $role->givePermissionTo(Permission::findOrCreate('hr.delegations.view'));
    $this->routeEmployee->assignRole($role);
    $this->actingAs($this->routeEmployee);
});

test('route returns one way kilometers and hours using server side Google key', function () {
    Http::fake(['routes.googleapis.com/*' => Http::response([
        'routes' => [['distanceMeters' => 123450, 'duration' => '5400s']],
    ])]);
    $this->postJson(route('hr.route.calculate'), ['origin' => 'Cieszyn', 'destination' => 'Kraków'])
        ->assertOk()->assertExactJson(['distance_km' => 123.5, 'hours' => 1.5]);
    Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'secret-test-key')
        && $request['origin']['address'] === 'Cieszyn'
        && $request['destination']['address'] === 'Kraków'
        && $request['travelMode'] === 'DRIVE');
});

test('route handles provider rejection without leaking provider details or secret', function (int $status, string $message) {
    Http::fake(['routes.googleapis.com/*' => Http::response([
        'error' => ['message' => 'secret-test-key private provider details'],
    ], $status)]);
    $this->postJson(route('hr.route.calculate'), ['origin' => 'Cieszyn', 'destination' => 'Kraków'])
        ->assertUnprocessable()->assertJsonValidationErrors('route')->assertSee($message)
        ->assertDontSee('secret-test-key')->assertDontSee('private provider details');
})->with([
    [403, 'Google Cloud'], [401, 'Google Cloud'], [429, 'limit'],
    [500, 'Google Maps'], [400, 'Google'],
]);

test('route rejects missing or invalid provider values instead of returning zero', function (array $payload) {
    Http::fake(['routes.googleapis.com/*' => Http::response($payload)]);
    $this->postJson(route('hr.route.calculate'), ['origin' => 'A', 'destination' => 'B'])
        ->assertUnprocessable()->assertJsonValidationErrors('route');
})->with([
    [[]],
    [['routes' => [['distanceMeters' => 1500]]]],
    [['routes' => [['distanceMeters' => 1500, 'duration' => 'nonsense']]]],
    [['routes' => [['distanceMeters' => -10, 'duration' => '500s']]]],
    [['routes' => [['distanceMeters' => 1500, 'duration' => '0s']]]],
]);

test('route timeout and places timeout do not produce server errors or expose keys', function () {
    Http::fake(fn () => throw new ConnectionException('secret-test-key'));
    $this->postJson(route('hr.route.calculate'), ['origin' => 'A', 'destination' => 'B'])
        ->assertUnprocessable()->assertJsonValidationErrors('route')->assertDontSee('secret-test-key');
    $this->getJson(route('hr.places.autocomplete', ['q' => 'Cieszyn']))
        ->assertOk()->assertExactJson(['suggestions' => []]);
});

test('route validates input and missing key without contacting Google', function () {
    Http::fake();
    $this->postJson(route('hr.route.calculate'), ['origin' => '', 'destination' => 'B'])
        ->assertUnprocessable()->assertJsonValidationErrors('origin');
    config(['services.google.maps_key' => null]);
    $this->postJson(route('hr.route.calculate'), ['origin' => 'A', 'destination' => 'B'])
        ->assertUnprocessable()->assertJsonValidationErrors('route');
    Http::assertNothingSent();
});

test('route and places enforce HR permissions and module switch after controller split', function () {
    Http::fake();
    $this->actingAs(User::factory()->create());
    $this->postJson(route('hr.route.calculate'), ['origin' => 'A', 'destination' => 'B'])->assertForbidden();
    $this->getJson(route('hr.places.autocomplete', ['q' => 'Cieszyn']))->assertForbidden();
    CompanySettings::create(['name' => 'Test', 'enabled_modules' => []]);
    $this->actingAs($this->routeEmployee);
    $this->postJson(route('hr.route.calculate'), ['origin' => 'A', 'destination' => 'B'])
        ->assertForbidden()->assertSee('Ten modu', false);
    Http::assertNothingSent();
});
