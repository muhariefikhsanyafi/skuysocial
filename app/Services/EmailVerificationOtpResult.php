<?php

namespace App\Services;

enum EmailVerificationOtpResult: string
{
    case Sent = 'sent';
    case Cooldown = 'cooldown';
    case DeliveryFailed = 'delivery_failed';
    case AlreadyVerified = 'already_verified';
    case Verified = 'verified';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case AttemptLimit = 'attempt_limit';
}
