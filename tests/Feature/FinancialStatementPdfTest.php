<?php

use App\Models\Company;
use App\Models\CompanyReliabilityFile;
use App\Models\User;
use App\Services\FinancialPdfText;
use App\Services\FinancialStatementPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    Storage::fake('local');
    config(['services.anthropic.key' => 'test-key']);
    $this->company = Company::create(['name' => 'PDF test', 'nip' => '5260250274', 'company_type' => 'client', 'status' => 'active']);
    $this->pages = [1 => 'NIP 5260250274 PLN jednostkowe sprawozdanie', 2 => '2024 Przychody 123,45 Zobowiązania 20,00'];
    $this->data = ['nip' => '5260250274', 'currency' => 'PLN', 'scope' => 'standalone', 'rows' => [['year' => 2024, 'scale' => 1, 'revenue' => ['value' => '123.45', 'page' => 2, 'quote' => 'Przychody 123,45'], 'profit' => null, 'equity' => null, 'liabilities' => null]]];
    $this->data['rows'][0]['unit_evidence'] = ['page' => 1, 'quote' => 'PLN'];
    Storage::put('sample.pdf', '%PDF-test');
    $this->path = Storage::path('sample.pdf');
    $this->mock(FinancialPdfText::class, function ($mock) {
        $mock->shouldReceive('pages')->andReturn($this->pages);
        $mock->shouldReceive('select')->andReturn($this->pages);
    });
});

test('PDF extraction is bounded cached and held for human confirmation', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'end_turn', 'content' => [['text' => json_encode($this->data)]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 100]])]);
    $service = app(FinancialStatementPdf::class);
    $result = $service->parse($this->path, $this->company);
    expect($result['rows'])->toBe([])->and($result['pdf_proposals'][0]['revenue'])->toBe('123.45')->and($result['pdf_proposals'][0]['profit'])->toBeNull();
    expect($service->parse($this->path, $this->company))->toBe($result);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['max_tokens'] === 2200 && $request['model'] === 'claude-haiku-4-5-20251001');
});

test('failed paid PDF extraction is not retried', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([], 503)]);
    foreach ([1, 2] as $attempt) {
        expect(fn () => app(FinancialStatementPdf::class)->parse($this->path, $this->company))->toThrow(ValidationException::class);
    }
    Http::assertSentCount(1);
});

test('missing configuration and daily cap do not send requests', function () {
    config(['services.anthropic.key' => null]);
    expect(fn () => app(FinancialStatementPdf::class)->parse($this->path, $this->company))->toThrow(ValidationException::class);
    config(['services.anthropic.key' => 'test']);
    foreach (range(1, 20) as $i) {
        DB::table('financial_pdf_reads')->insert(['cache_key' => hash('sha256', (string) $i), 'created_at' => now()]);
    }
    expect(fn () => app(FinancialStatementPdf::class)->parse($this->path, $this->company))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

test('PDF validation rejects wrong company currency scope evidence amounts and years', function () {
    foreach (['nip' => '1111111111', 'currency' => 'EUR', 'scope' => 'consolidated'] as $field => $value) {
        $data = array_replace($this->data, [$field => $value]);
        expect(fn () => app(FinancialStatementPdf::class)->validate($data, '5260250274', $this->pages))->toThrow(ValidationException::class);
    }
    foreach ([['page', 9], ['quote', 'invented'], ['value', '999.00']] as [$field, $value]) {
        $data = $this->data;
        $data['rows'][0]['revenue'][$field] = $value;
        expect(fn () => app(FinancialStatementPdf::class)->validate($data, '5260250274', $this->pages))->toThrow(ValidationException::class);
    }
    $this->data['rows'][0]['year'] = 1999;
    expect(fn () => app(FinancialStatementPdf::class)->validate($this->data, '5260250274', $this->pages))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

test('selection preserves page numbers and bounds text', function () {
    $pages = array_fill(1, 80, str_repeat('Przychody kapitał własny zobowiązania 2024 ', 500));
    $selected = (new FinancialPdfText)->select($pages);
    expect(count($selected))->toBeLessThanOrEqual(8)->and(mb_strlen(implode('', $selected)))->toBeLessThanOrEqual(32000)->and(array_key_first($selected))->toBe(1);
});

test('amount evidence handles spaced table columns negative amounts and scales', function () {
    $pages = [1 => 'w tys. PLN', 2 => '2024 Kapitał  1 234,00  (5 678,90)'];
    foreach (['1234.00', '-5678.90'] as $value) {
        $this->data['rows'][0]['revenue'] = ['value' => $value, 'page' => 2, 'quote' => 'Kapitał  1 234,00  (5 678,90)'];
        $this->data['rows'][0]['scale'] = 1000;
        $this->data['rows'][0]['unit_evidence'] = ['page' => 1, 'quote' => 'w tys. PLN'];
        $result = app(FinancialStatementPdf::class)->validate($this->data, '5260250274', $pages);
        expect($result[0]['revenue'])->toBe(number_format((float) $value * 1000, 2, '.', ''));
    }
});

test('missing NIP needs exact reporting entity name and explicit identity warning', function () {
    $data = $this->data;
    $data['nip'] = null;
    $data['entity_name'] = 'INTROL-ENERGOMONTAŻ Sp. z o.o.';
    $service = app(FinancialStatementPdf::class);
    expect($service->validate($data, '5260250274', $this->pages, 'INTROL-ENERGOMONTAŻ SPÓŁKA Z OGRANICZONĄ ODPOWIEDZIALNOŚCIĄ')[0]['revenue'])->toBe('123.45');
    expect(fn () => $service->validate($data, '5260250274', $this->pages, 'Inna firma'))->toThrow(ValidationException::class);
});

test('only authorised user can confirm a PDF belonging to the company without another AI request', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $rows = app(FinancialStatementPdf::class)->validate($this->data, '5260250274', $this->pages);
    $file = CompanyReliabilityFile::create(['company_id' => $this->company->id, 'name' => 'sample.pdf', 'stored_path' => 'sample.pdf', 'size' => 9, 'parsed_finances' => ['nip' => '5260250274', 'rows' => [], 'pdf_proposals' => $rows]]);
    $this->actingAs($admin)->get(route('companies.reliability.show', $this->company))->assertOk()
        ->assertSee('Potwierdź odczyt i uzupełnij pola')->assertSee('Przychody 123,45')
        ->assertDontSee('name="finances[0][revenue]" value="123,45"', false);
    $this->actingAs($admin)->post(route('companies.reliability.files.import', [$this->company, $file]), ['confirm_pdf' => 1])->assertRedirect();
    expect($file->fresh()->parsed_finances['rows'][0]['revenue'])->toBe('123.45');
    $other = Company::create(['name' => 'Other', 'nip' => '1234567890', 'company_type' => 'client', 'status' => 'active']);
    $this->post(route('companies.reliability.files.import', [$other, $file]), ['confirm_pdf' => 1])->assertNotFound();
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_user'));
    $this->actingAs($client)->post(route('companies.reliability.files.import', [$this->company, $file]), ['confirm_pdf' => 1])->assertForbidden();
    Http::assertNothingSent();
});

test('PDF upload is retained without AI configuration and can be analysed later', function () {
    config(['services.anthropic.key' => null]);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin'));
    $upload = UploadedFile::fake()->createWithContent('statement.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    $this->actingAs($admin)->post(route('companies.reliability.files.store', $this->company), ['file' => $upload])->assertSessionHasNoErrors()->assertRedirect();
    $file = CompanyReliabilityFile::firstOrFail();
    expect($file->parsed_finances)->toBeNull();
    Storage::assertExists($file->stored_path);
    $this->get(route('companies.reliability.show', $this->company))->assertOk()->assertSee('Odczytaj PDF');
    Http::assertNothingSent();
});
