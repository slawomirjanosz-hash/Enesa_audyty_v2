<?php

use App\Models\Company;
use App\Models\CompanyReliabilityFile;
use App\Models\User;
use App\Services\FinancialStatementXml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

function financialXmlFixture(string $unit = 'SprFinJednostkaInnaWZlotych', string $variant = 'RZiSKalk'): string
{
    $profit = $variant === 'RZiSKalk' ? 'O' : 'L';

    return '<s:Dokument xmlns:s="urn:test"><s:JednostkaInna><s:Naglowek><s:OkresOd>2025-01-01</s:OkresOd><s:OkresDo>2025-12-31</s:OkresDo><s:KodSprawozdania>'.$unit.'</s:KodSprawozdania></s:Naglowek><s:WprowadzenieDoSprawozdaniaFinansowego><s:P_1><s:P_1D>8671871281</s:P_1D></s:P_1></s:WprowadzenieDoSprawozdaniaFinansowego><s:Bilans><s:Pasywa><s:Pasywa_A><s:KwotaA>200.12</s:KwotaA><s:KwotaB>190</s:KwotaB></s:Pasywa_A><s:Pasywa_B><s:KwotaA>300</s:KwotaA><s:KwotaB>250</s:KwotaB></s:Pasywa_B></s:Pasywa></s:Bilans><s:RZiS><s:'.$variant.'><s:A><s:KwotaA>1000.45</s:KwotaA><s:KwotaB>900</s:KwotaB></s:A><s:'.$profit.'><s:KwotaA>-10.05</s:KwotaA><s:KwotaB>0</s:KwotaB></s:'.$profit.'></s:'.$variant.'></s:RZiS></s:JednostkaInna></s:Dokument>';
}

test('financial XML imports both variants and scales amounts without replacing missing values with zero', function () {
    $parser = app(FinancialStatementXml::class);
    $rows = $parser->parse(financialXmlFixture(), '8671871281', 'sample.xml');
    expect($rows[0])->toMatchArray(['year' => 2025, 'revenue' => '1000.45', 'profit' => '-10.05', 'equity' => '200.12', 'liabilities' => '300.00']);
    expect($rows[1])->toMatchArray(['year' => 2024, 'profit' => '0.00']);
    $rows = $parser->parse(financialXmlFixture('SprFinJednostkaInnaWTysiacach', 'RZiSPor'), '8671871281', 'sample.xml');
    expect($rows[0]['revenue'])->toBe('1000450.00');
    expect($rows[0]['profit'])->toBe('-10050.00');
    $rows = $parser->parse(str_replace('<s:KwotaA>200.12</s:KwotaA>', '', financialXmlFixture()), '8671871281', 'sample.xml');
    expect($rows[0]['equity'])->toBeNull();
});

test('financial XML rejects identity mismatch unknown units entities invalid dates and multiple statements', function () {
    $parser = app(FinancialStatementXml::class);
    foreach ([str_replace('8671871281', '5260250274', financialXmlFixture()),
        financialXmlFixture('Unknown'), '<!DOCTYPE x [<!ENTITY leak SYSTEM "file:///etc/passwd">]>'.financialXmlFixture(),
        str_replace('2025-12-31', '2025-02-31', financialXmlFixture()), '<broken',
        '<wrapper>'.financialXmlFixture().financialXmlFixture().'</wrapper>'] as $xml) {
        expect(fn () => $parser->parse($xml, '8671871281', 'sample.xml'))->toThrow(ValidationException::class);
    }
});

test('XML upload fills report defaults and existing files can be imported with permission checks', function () {
    Storage::fake('local');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('client_admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Test', 'nip' => '8671871281', 'status' => 'active']);
    $this->actingAs($admin)->post(route('companies.reliability.files.store', $company), ['file' => UploadedFile::fake()->createWithContent('sample.xml', financialXmlFixture())])->assertSessionHasNoErrors();
    $file = CompanyReliabilityFile::firstOrFail();
    expect($file->parsed_finances['rows'][0]['revenue'])->toBe('1000.45');
    $this->get(route('companies.reliability.show', $company))->assertOk()->assertSee('value="1 000,45 zł"', false)->assertSee('value="-10,05 zł"', false);
    $file->update(['parsed_finances' => null]);
    $this->post(route('companies.reliability.files.import', [$company, $file]))->assertSessionHasNoErrors()->assertRedirect();
    expect($file->fresh()->parsed_finances)->not->toBeNull();
    $other = Company::create(['name' => 'Other', 'nip' => '5260250274']);
    $this->post(route('companies.reliability.files.import', [$other, $file]))->assertNotFound();
    $client = User::factory()->create();
    $client->assignRole('client_admin');
    $this->actingAs($client)->post(route('companies.reliability.files.import', [$company, $file]))->assertForbidden();
    $company->update(['nip' => '5260250274']);
    $this->actingAs($admin)->get(route('companies.reliability.show', $company))->assertDontSee('value="1 000,45 zł"', false);
    $this->post(route('companies.reliability.files.import', [$company, $file]))->assertSessionHasErrors('file');
});
