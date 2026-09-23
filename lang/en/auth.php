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
        'remember_30_days' => 'Remember me for 30 days',
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
        'title' => 'Verify your email address',
        'intro' => 'To continue to SkuySocial, request a 6-digit code for :email below.',
        'sent_intro' => 'A 6-digit code was sent to :email. Enter it below; it expires in 10 minutes.',
        'code_label' => '6-digit code',
        'code_hint' => 'Enter the six digits from your email. The code can be used once.',
        'verify' => 'Verify email',
        'resend' => 'Send a new code',
        'continue' => 'Continue to SkuySocial',
        'resend_cooldown' => 'You can request another code in :seconds seconds.',
        'sent' => 'A new 6-digit code was sent to your email address.',
        'mail_failed' => "We couldn't send a verification code right now. Your account is still unverified. You can safely try again.",
        'cooldown' => 'Please wait for the resend cooldown before requesting another code.',
        'invalid' => 'That code is invalid. Check the six digits and try again.',
        'expired' => 'That code has expired. Request a new code to continue.',
        'attempt_limit' => 'Too many incorrect attempts. Request a new code to try again.',
        'already_verified' => 'Your email address is already verified.',
        'verified' => 'Your email address is verified.',
        'code_expired' => 'Your current code has expired. Request a new code to continue.',
        'attempt_limit_reached' => 'This code has reached its attempt limit. Request a new one after the cooldown.',
        'mail_subject' => 'Your SkuySocial verification code',
        'mail_title' => 'Verify your email address',
        'mail_intro' => 'Use this 6-digit code to verify your SkuySocial account:',
        'mail_expiration' => 'This code expires in 10 minutes and can be used once.',
        'mail_ignore' => 'If you did not create this account, you can ignore this message.',
        'mail_footer' => 'SkuySocial account verification',
    ],

    'security' => [
        'verification_failed' => 'Security verification failed. Please try again.',
    ],
];
