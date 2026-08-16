<?php

use App\Models\User;

it('creates initials from the first name and surname', function () {
    expect((new User(['name' => 'Jan Kowalski']))->initials())->toBe('JK')
        ->and((new User(['name' => 'Anna Maria Nowak']))->initials())->toBe('AN')
        ->and((new User(['name' => 'Łukasz Żółć']))->initials())->toBe('ŁŻ')
        ->and((new User(['name' => 'Madonna']))->initials())->toBe('M')
        ->and((new User(['name' => '   ']))->initials())->toBe('?');
});
