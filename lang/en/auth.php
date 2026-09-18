<?php

return [
    'failed' => 'The provided credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many sign-in attempts. Please try again in :seconds seconds.',

    'common' => [
        'page_title' => 'Authentication',
        'name' => 'NAME',
        'username' => 'USERNAME',
        'email_address' => 'EMAIL ADDRESS',
        'password' => 'PASSWORD',
        'password_confirmation' => 'CONFIRM PASSWORD',
        'show_password' => 'Show password',
        'hide_password' => 'Hide password',
        'log_out' => 'LOG OUT',
    ],

    'login' => [
        'title' => 'Sign in to SkuySocial',
        'intro' => 'Continue the conversations you follow.',
        'identifier_label' => 'Email or username',
        'password_label' => 'Password',
        'forgot_password' => 'Forgot password?',
        'no_account' => "Don't have an account?",
        'submit' => 'Sign in',
        'register' => 'Create account',
    ],

    'register' => [
        'title' => 'Create your SkuySocial account',
        'intro' => 'Start conversations and find topics you want to follow.',
        'name_label' => 'Name',
        'username_label' => 'Username',
        'username_hint' => '(no spaces)',
        'username_format_hint' => 'Username cannot contain spaces.',
        'email_label' => 'Email address',
        'password_label' => 'Password',
        'password_confirmation_label' => 'Confirm password',
        'has_account' => 'Already have an account?',
        'sign_in' => 'Sign in',
        'submit' => 'Create account',
    ],

    'forgot_password' => [
        'title' => 'Forgot your password?',
        'intro' => "Enter the email address for your account. We'll send you a link to reset your password.",
        'email_label' => 'Email address',
        'back_to_login' => 'Back to sign in',
        'submit' => 'Send reset link',
    ],

    'reset_password' => [
        'title' => 'Reset your password',
        'intro' => 'Create a new password for your account.',
        'email_label' => 'Email address',
        'new_password' => 'New password',
        'password_confirmation' => 'Confirm new password',
        'back_to_login' => 'Back to sign in',
        'submit' => 'Reset password',
    ],

    'confirm_password' => [
        'title' => 'Confirm Password',
        'intro' => 'This is a secure area. Please confirm your password before continuing.',
        'submit' => 'CONFIRM',
    ],

    'verify_email' => [
        'title' => 'Verify Email Address',
        'intro' => 'Before continuing, please verify your email address using the link we sent you. If you did not receive the email, we can send another.',
        'sent' => 'A new verification link has been sent to your email address.',
        'resend' => 'RESEND VERIFICATION EMAIL',
    ],

    'security' => [
        'verification_failed' => 'Security verification failed. Please try again.',
    ],
];
