<?php

return [
    'social' => [
        'heading' => 'SkuySocial notifications',
        'actor_fallback' => 'Someone',
        'like' => ':actor liked your thread.',
        'comment' => ':actor commented on your thread.',
        'reply' => ':actor replied to a comment on your thread.',
        'follow' => ':actor started following you.',
        'direct_message' => 'New message from :actor.',
    ],
    'test' => [
        'heading' => 'SkuySocial notifications',
        'body' => 'This is a test notification for your account.',
    ],
    'command' => [
        'configuration_missing' => 'OneSignal test push is not configured.',
        'user_not_found' => 'No user was found for that internal user ID.',
        'sent' => 'The test push was accepted by OneSignal.',
        'no_subscribers' => 'No subscribed device matched this user.',
        'failed' => 'OneSignal could not send the test push.',
    ],
];
