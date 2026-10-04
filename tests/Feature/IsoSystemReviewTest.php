<?php

use App\Models\IsoSectionDocument;
use App\Models\IsoStakeholderReview;
use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoStakeholderQuestionnaire;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

function systemFixture(): array
{
    [$audit, $client, $staff, $profile, $factor] = stakeholderFixture();
    $approval = ['user_id' => $staff->id, 'name' => $staff->name, 'at' => now()->toIso8601String()];
    $factor->update(['status' => 'approved', 'auditor_approval' => $approval]);
    $party = IsoStakeholderReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'source_hash' => app(IsoStakeholderQuestionnaire::class)->hash($profile, $factor), 'status' => 'approved', 'lock_version' => 1, 'answers' => [], 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => $approval]);
    foreach (['4-1' => $factor, '4-2' => $party] as $section => $review) {
        $doc = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => $section, 'scope' => 'client', 'title' => 'Test źródła', 'document_year' => now()->year, 'version_number' => '1', 'original_filename' => 'test.pdf', 'stored_path' => 'test/'.$section.'.pdf', 'mime_type' => 'application/pdf', 'size' => 4, 'content_base64' => base64_encode('%PDF'), 'uploaded_by' => $staff->id]);
        $review->update(['document_id' => $doc->id]);
    }

    return [$audit, $client, $staff, $profile, $factor, $party];
}

function systemAnswers(string $section, array $sources): array
{
    $service = app(IsoSystemQuestionnaire::class);
    $answers = $service->seed($section, $sources);
    for ($pass = 0; $pass < 2; $pass++) {
        foreach ($service->sections($section, $answers, $sources) as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['required'] && $service->visible($field, $answers) && ! filled(data_get($answers, $field['key']))) {
                    $value = $field['options'] ? array_key_first($field['options']) : ($field['type'] === 'date' ? today()->format('Y-m-d') : 'Przykład: opis potwierdzony podczas przeglądu zakładu.');
                    Arr::set($answers, $field['key'], $field['type'] === 'multi' ? [$value] : $value);
                }
            }
        }
    }

    return $answers;
}

test('scope and system preserve source codes and conditional requirements', function () {
    $service = app(IsoSystemQuestionnaire::class);
    expect($service->schema('4-3')['konsekwencje_reguly'])->toHaveCount(9);
    expect($service->schema('4-4')['procesy'])->toHaveCount(11);
    expect($service->schema('4-4')['lista_kontrolna'])->toHaveCount(37);
    $linked = ['facts' => ['FAKT_ODBIORCY_CO2' => 'tak'], 'parties' => []];
    expect($service->consequences($linked))->not->toHaveKey('K-01');
    $linked['parties'] = [['kod' => 'STK-Z-12']];
    expect($service->consequences($linked))->toHaveKey('K-01');
    $sources = ['facts' => ['FAKT_FLOTA' => 'tak'], 'ready' => true];
    $answers = systemAnswers('4-3', $sources);
    expect($service->progress('4-3', $answers, $sources)['percent'])->toBe(100);
    expect(data_get($answers, 'ENERGIA.PALIWA.status'))->toBe('wystepuje');
    unset($answers['SPRAWDZONE']['data']);
    expect(fn () => $service->normalize('4-3', $answers, $sources, true))->toThrow(ValidationException::class);
    $answers = systemAnswers('4-4', $sources);
    $answers['ODP']['SYS-02'] = 'nie';
    expect(fn () => $service->normalize('4-4', $answers, $sources, true))->toThrow(ValidationException::class);
    $answers['ODP']['SYS-02'] = 'tak';
    $answers['TS']['T-1'] = ['stan' => 'nikt', 'start' => 'Plan wdrożenia zastępstwa'];
    expect($service->checklistState('test', $answers))->toContain('Wymaga uwagi');
    $answers['ODP']['SYS-01'] = 'nadbudowa';
    expect(fn () => $service->normalize('4-4', $answers, $sources, true))->toThrow(ValidationException::class);
});

test('scope and system approvals documents and source invalidation work end to end', function () {
    [$audit, $client, $staff, $profile, $factor, $party] = systemFixture();
    $service = app(IsoSystemQuestionnaire::class);
    foreach (['4-3', '4-4'] as $section) {
        $sources = $service->sources($profile, $section);
        expect($sources['ready'])->toBeTrue();
        $clientUrl = route('client.audits.system.update', [$audit, $profile, $section]);
        $staffUrl = route('audits.system.update', [$audit, $profile, $section]);
        $answers = systemAnswers($section, $sources);
        $payload = ['operation' => 'submit', 'answers' => $answers, 'lock_version' => 0, 'source_hash' => $sources['hash'], 'complete_form' => 1];
        $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, $section]))->assertOk()->assertSee('Wypełnienie')->assertDontSee('id="system-example"', false);
        $this->post($clientUrl, $payload)->assertSessionHasNoErrors();
        $review = IsoSystemReview::where('section', $section)->sole();
        expect($review->client_changes)->toBeNull();
        $this->post($clientUrl, array_replace($payload, ['operation' => 'approve', 'lock_version' => 1]))->assertForbidden();
        if ($section === '4-4') {
            foreach ($service->schema($section)['lista_kontrolna'] as $i => $item) {
                if ($item['auto'] === null) {
                    $answers['CHECK'][$i] = ['stan' => 'tak', 'uwagi' => 'Sprawdzono dowody.'];
                }
            }
            $this->actingAs($staff)->post($staffUrl, array_replace($payload, ['operation' => 'save', 'lock_version' => 1, 'answers' => $answers]))->assertSessionHasNoErrors();
            expect($review->fresh()->client_approval)->toBeNull();
            $this->actingAs($client)->post($clientUrl, array_replace($payload, ['lock_version' => 2]))->assertSessionHasNoErrors();
        }
        $version = $review->fresh()->lock_version;
        $this->actingAs($staff)->post($staffUrl, ['operation' => 'approve', 'lock_version' => $version, 'source_hash' => $sources['hash']])->assertSessionHasNoErrors();
        $review->refresh();
        expect($review->status)->toBe('approved');
        $pdfUrl = route('audits.system.pdf', [$audit, $profile, $section]);
        $this->post($pdfUrl, ['lock_version' => $review->lock_version])->assertRedirect()->assertSessionHasNoErrors();
        $document = IsoSectionDocument::findOrFail($review->fresh()->document_id);
        expect($document->section_id)->toBe($section)->and($document->contents())->toStartWith('%PDF');
        if (getenv('ISO_SYSTEM_QA')) {
            file_put_contents(base_path('tmp/iso-'.$section.'.pdf'), $document->contents());
            file_put_contents(base_path('tmp/iso-'.$section.'.html'), $this->get(route('audits.system.show', [$audit, $profile, $section]))->getContent());
        }
        $this->get(route('audits.show', [$audit, 'tab' => 'iso50001', 'section' => $section]))->assertOk()->assertSee('Otwórz ankietę '.str_replace('-', '.', $section));
        $this->post($staffUrl, ['operation' => 'approve', 'lock_version' => 0, 'source_hash' => $sources['hash']])->assertStatus(409);
    }
    $scope = IsoSystemReview::where('section', '4-3')->sole();
    $sources = $service->sources($profile, '4-3');
    $answers = $scope->answers;
    $answers['ZAKRES']['opis'] = 'Zmieniony zakres';
    $this->actingAs($staff)->post(route('audits.system.update', [$audit, $profile, '4-3']), ['operation' => 'save', 'lock_version' => $scope->lock_version, 'source_hash' => $sources['hash'], 'answers' => $answers, 'complete_form' => 1])->assertSessionHasNoErrors();
    expect($scope->fresh()->status)->toBe('auditor_corrected')->and($scope->fresh()->document_id)->toBeNull();
    expect($service->sources($profile, '4-4')['ready'])->toBeFalse();
    $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, '4-3']))->assertSee('system-changed')->assertSee('Zmieniony zakres');
    $system = IsoSystemReview::where('section', '4-4')->sole();
    $this->post(route('client.audits.system.pdf', [$audit, $profile, '4-4']), ['lock_version' => $system->lock_version])->assertStatus(409);
    expect(IsoSystemReview::count())->toBe(2);
});

test('system denies foreign clients and read only staff and prevents premature publication', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $foreign = User::factory()->create();
    $foreign->assignRole('client_admin');
    $this->actingAs($foreign)->get(route('client.audits.system.show', [$audit, $profile, '4-3']))->assertNotFound();
    $this->actingAs($client)->get(route('client.audits.system.show', [$audit, $profile, '4-5']))->assertNotFound();
    $service = app(IsoSystemQuestionnaire::class);
    $sources = $service->sources($profile, '4-4');
    expect($sources['ready'])->toBeFalse();
    $this->post(route('client.audits.system.update', [$audit, $profile, '4-4']), ['operation' => 'submit', 'lock_version' => 0, 'source_hash' => $sources['hash'], 'answers' => systemAnswers('4-4', $sources), 'complete_form' => 1])->assertSessionHasErrors('answers.sources');
    expect(IsoSystemReview::count())->toBe(0);
    $reader = User::factory()->create();
    $reader->assignRole(Role::findOrCreate('system_test_reader', 'web'));
    $reader->givePermissionTo('audits.view');
    $this->actingAs($reader)->get(route('audits.system.show', [$audit, $profile, '4-3']))->assertOk();
    $this->post(route('audits.system.update', [$audit, $profile, '4-3']), [])->assertForbidden();
    $this->post(route('audits.system.pdf', [$audit, $profile, '4-3']), ['lock_version' => 0])->assertForbidden();
});
