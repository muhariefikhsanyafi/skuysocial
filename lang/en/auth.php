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
        'log_out' => 'LOG OUT',
    ],

    'login' => [
        'title' => 'Sign In',
        'forgot_password' => 'Forgot password?',
        'no_account' => "Don't have an account?",
        'submit' => 'SIGN IN',
        'register' => 'SIGN UP',
    ],

    'register' => [
        'title' => 'Create Account',
        'username_hint' => '(no spaces)',
        'username_format_hint' => 'Username cannot contain spaces.',
        'has_account' => 'Already have an account?',
        'submit' => 'SIGN UP',
    ],

    'forgot_password' => [
        'title' => 'Forgot Password',
        'intro' => 'Enter your email address and we will send you a link to reset your password.',
        'back_to_login' => 'Back to Sign In',
        'submit' => 'SEND RESET LINK',
    ],

    'reset_password' => [
        'title' => 'Reset Password',
        'new_password' => 'NEW PASSWORD',
        'submit' => 'RESET PASSWORD',
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
