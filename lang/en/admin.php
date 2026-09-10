<?php

return [
    'navigation' => [
        'users' => 'Users',
    ],
    'labels' => [
        'user' => 'User',
        'users' => 'Users',
    ],
    'columns' => [
        'name' => 'Name',
        'username' => 'Username',
        'email' => 'Email address',
        'role' => 'Role',
        'email_verification' => 'Email verification',
        'public_verification' => 'Public verification',
        'locale' => 'Language',
        'created_at' => 'Joined',
        'updated_at' => 'Updated',
        'bio' => 'Bio',
        'id' => 'Account ID',
    ],
    'filters' => [
        'role' => 'Role',
        'email_verification' => 'Email verification',
        'public_verification' => 'Public verification',
        'locale' => 'Language',
    ],
    'values' => [
        'administrator' => 'Administrator',
        'user' => 'User',
        'unknown_role' => 'Unknown role',
        'verified' => 'Verified',
        'unverified' => 'Unverified',
        'not_set' => 'Not set',
    ],
    'locales' => [
        'id' => 'Bahasa Indonesia',
        'en' => 'English',
    ],
    'actions' => [
        'view' => 'View',
    ],
    'sections' => [
        'identity' => 'Identity',
        'access' => 'Access',
        'profile' => 'Profile',
        'metadata' => 'Account information',
    ],
    'table' => [
        'empty_heading' => 'No users found',
        'empty_description' => 'No account matches the current search or filters.',
    ],
];
