<?php

use Illuminate\Support\Facades\Validator;

test('system validation and authentication messages are in Polish', function () {
    $validator = Validator::make([
        'email' => 'niepoprawny-adres',
        'name' => '',
    ], [
        'email' => ['required', 'email'],
        'name' => ['required'],
    ]);

    expect($validator->errors()->first('email'))->toBe('Pole adres e-mail musi zawierać prawidłowy adres e-mail.')
        ->and($validator->errors()->first('name'))->toBe('Pole nazwa jest wymagane.')
        ->and(__('auth.failed'))->toBe('Podane dane logowania są nieprawidłowe.')
        ->and(__('passwords.token'))->toBe('Token zmiany hasła jest nieprawidłowy lub wygasł.');
});
