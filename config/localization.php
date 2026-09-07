<?php

return [
    'supported' => [
        'id' => 'Bahasa Indonesia',
        'en' => 'English',
    ],

    'default' => env('APP_LOCALE', 'id'),

    'session_key' => 'locale',

    'cookie_name' => 'skuysocial_locale',

    'cookie_lifetime' => 525600,
];
