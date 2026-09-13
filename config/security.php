<?php

return [
    'totp' => ['enabled' => env('SUPERADMIN_TOTP_ENABLED', true)],
    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
        'hostname' => env('TURNSTILE_HOSTNAME', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    ],
    'sms' => [
        'enabled' => env('SUPERADMIN_SMS_ENABLED', false),
        'token' => env('SMSAPI_TOKEN'),
        'sender' => env('SMSAPI_SENDER'),
        'phone' => env('SUPERADMIN_SMS_PHONE'),
    ],
];
