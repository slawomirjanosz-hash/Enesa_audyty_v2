<?php

use App\Models\User;

test('project member picker is collapsed and preserves selected member ids', function () {
    $users = collect([
        (new User)->forceFill(['id' => 11, 'name' => 'Anna Kowalska']),
        (new User)->forceFill(['id' => 22, 'name' => 'Jan Nowak']),
    ]);
    $html = view('projects.partials.member-picker', ['users' => $users, 'selectedMembers' => ['22']])->render();
    expect($html)->toContain('data-member-picker>')->toContain('Wybrano: 1')
        ->toContain('value="22" checked')->toContain('value="11" >')
        ->toContain('<span>Anna Kowalska</span>')->toContain('data-member-search')
        ->not->toContain('data-member-picker open');
});
