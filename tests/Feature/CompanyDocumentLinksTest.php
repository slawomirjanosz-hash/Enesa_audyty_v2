<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('company links are saved displayed and deleted only within their company', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $company = Company::create(['name' => 'Firma A', 'company_type' => 'client', 'status' => 'active']);
    $other = Company::create(['name' => 'Firma B', 'company_type' => 'client', 'status' => 'active']);
    $this->actingAs($admin)->post(route('companies.document-links.store', $company), [
        'name' => 'Cennik Office', 'url' => 'https://example.com/cennik.xlsx',
    ])->assertRedirect(route('companies.show', $company).'#documents');
    $link = $company->documentLinks()->sole();
    $this->get(route('companies.show', $company))->assertOk()->assertSee('Ważne linki firmy')->assertSee('Cennik Office')->assertSee('data-sort-value="'.$link->created_at->toIso8601String().'"', false);
    $this->get(route('companies.show', $other))->assertOk()->assertDontSee('Cennik Office');
    $this->delete(route('companies.document-links.destroy', [$other, $link]))->assertNotFound();
    foreach (['javascript:alert(1)', 'https://user:pass@example.com'] as $url) {
        $this->post(route('companies.document-links.store', $company), ['name' => 'Link', 'url' => $url])->assertSessionHasErrors('url');
    }
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $this->actingAs($client)->post(route('companies.document-links.store', $company), ['name' => 'Link', 'url' => 'https://example.com'])->assertForbidden();
    $this->delete(route('companies.document-links.destroy', [$company, $link]))->assertForbidden();
    $this->actingAs($admin)->delete(route('companies.document-links.destroy', [$company, $link]))->assertRedirect();
    expect($company->documentLinks()->count())->toBe(0);
});
