<?php

use App\Models\FormDraft;
use App\Models\IsoPlantProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('drafts are encrypted private working copies with optimistic concurrency and discard tombstones', function () {
    [$admin, $project] = boardFixture();
    $key = hash('sha256', 'project-form');
    $url = route('form-drafts.update', $key);
    $original = $project->name;
    $payload = ['base' => 'fingerprint', 'snapshot' => ['fields' => [['name' => 'name', 'value' => 'Poufny szkic']]]];
    $this->actingAs($admin)->getJson(route('form-drafts.show', $key))->assertOk()->assertJsonPath('revision', 0)->assertJsonPath('payload', null);
    $this->putJson($url, ['revision' => 0, 'payload' => $payload])->assertOk()->assertJsonPath('revision', 1);
    expect($project->fresh()->name)->toBe($original);
    expect(DB::table('form_drafts')->first()->payload)->not->toContain('Poufny szkic');
    $this->getJson(route('form-drafts.show', $key))->assertJsonPath('payload.snapshot.fields.0.value', 'Poufny szkic');
    $other = User::factory()->create();
    $this->actingAs($other)->getJson(route('form-drafts.show', $key))->assertJsonPath('payload', null);
    $this->putJson($url, ['revision' => 0, 'payload' => null])->assertOk();
    expect(FormDraft::where('user_id', $admin->id)->first()->payload)->toBe($payload);
    $this->actingAs($admin)->putJson($url, ['revision' => 0, 'payload' => $payload])->assertStatus(409);
    $this->putJson($url, ['revision' => 1, 'payload' => null])->assertOk()->assertJsonPath('revision', 2);
    $this->putJson($url, ['revision' => 1, 'payload' => $payload])->assertStatus(409);
    $this->getJson(route('form-drafts.show', $key))->assertJsonPath('payload', null);
});

test('failed manual saves preserve a draft and successful business saves consume only the matching revision', function () {
    [$admin, $project] = boardFixture();
    $key = hash('sha256', 'manual-project');
    $draft = FormDraft::create(['user_id' => $admin->id, 'form_key' => $key, 'revision' => 1, 'payload' => ['snapshot' => 'working']]);
    $this->actingAs($admin)->put(route('projects.update', $project), ['_draft_key' => $key, '_draft_revision' => 1, 'name' => ''])->assertSessionHasErrors();
    expect($draft->fresh()->payload)->not->toBeNull();
    $data = $project->only(['number', 'name', 'company_id', 'manager_id', 'status']);
    $data['contract_value'] = 0;
    $data['name'] = 'Zapis ręczny';
    $this->put(route('projects.update', $project), $data + ['_draft_key' => $key, '_draft_revision' => 0])->assertSessionHas('success');
    expect($draft->fresh()->payload)->not->toBeNull();
    $this->put(route('projects.update', $project), $data + ['_draft_key' => $key, '_draft_revision' => 1])->assertSessionHas('success');
    expect($draft->fresh()->payload)->toBeNull();
    expect($project->fresh()->name)->toBe('Zapis ręczny');
});

test('editable offer project audit and ISO forms expose draft controls without enabling approval autosave', function () {
    [$admin, $project, $audit] = boardFixture();
    $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertSee('data-autosave', false)->assertSee('form-drafts.js');
    $this->get(route('audits.show', $audit))->assertOk()->assertSee('data-autosave', false);
    $offerPage = $this->get(route('offers.create'))->assertOk()->assertSee('data-autosave', false)->assertSee('offer-drafts.js');
    [$isoAudit, $client] = plantFixture();
    $this->actingAs($client)->post(route('client.audits.plant-profile.create', $isoAudit), ['name' => 'Autozapis']);
    $profile = IsoPlantProfile::firstOrFail();
    $page = $this->get(route('client.audits.plant-profile.show', [$isoAudit, $profile]))->assertOk()->assertSee('data-autosave', false)->assertSee('form-drafts.js');
    if (getenv('FORM_DRAFT_QA')) {
        file_put_contents(base_path('tmp/draft-offer.html'), $offerPage->getContent());
        file_put_contents(base_path('tmp/draft-plant.html'), $page->getContent());
    }
});
