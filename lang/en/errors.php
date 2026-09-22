<?php

return [
    '404' => [
        'title' => 'This page is not available',
        'body' => 'The link you opened may have moved or may no longer be available.',
        'action' => 'Back to home',
    ],

    '419' => [
        'title' => 'Your session has expired',
        'body' => 'Reload the page and try again. If needed, sign in again to continue.',
        'action' => 'Back to home',
        'secondary_action' => 'Sign in again',
    ],

    '429' => [
        'title' => 'Too many requests',
        'body' => 'We received too many requests in a short time. Please wait a moment before trying again.',
        'action' => 'Back to home',
    ],

    '500' => [
        'title' => 'Something went wrong',
        'body' => 'We could not complete this request right now. Try again or return home.',
        'action' => 'Try again',
        'secondary_action' => 'Back to home',
    ],
];
