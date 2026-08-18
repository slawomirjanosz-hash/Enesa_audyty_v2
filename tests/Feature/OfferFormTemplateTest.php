<?php

use App\Models\OfferFormTemplate;
use App\Models\User;

test('admin copies an offer request form and edits it independently', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $fields = [[
        'type' => 'section',
        'title' => 'Dane instalacji',
        'fields' => [[
            'key' => 'moc',
            'label' => 'Moc instalacji',
            'type' => 'text',
            'required' => true,
        ]],
    ]];
    $original = OfferFormTemplate::create([
        'name' => 'Formularz audytu',
        'description' => 'Opis formularza',
        'fields' => $fields,
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('offer-forms.index'))
        ->assertOk()
        ->assertSee('Kopiuj formularz')
        ->assertSee('openCopyModal(', false);

    $this->actingAs($admin)
        ->post(route('offer-forms.store'), [
            'name' => 'Formularz audytu — kopia',
            'description' => $original->description,
            'fields' => json_encode($original->fields),
            'is_active' => '1',
        ])
        ->assertRedirect(route('offer-forms.index'))
        ->assertSessionHas('success');

    $copy = OfferFormTemplate::where('name', 'Formularz audytu — kopia')->firstOrFail();

    expect($copy->id)->not->toBe($original->id)
        ->and($copy->fields)->toBe($original->fields)
        ->and($copy->description)->toBe($original->description);
});
