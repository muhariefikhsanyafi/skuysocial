<?php

namespace App\Services;

class EmailVerificationOtpCodeGenerator
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }
}
