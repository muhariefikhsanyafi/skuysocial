<?php

namespace App\Enums;

enum ModerationReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Impersonation = 'impersonation';
    case PlatformAbuse = 'platform_abuse';
    case SecurityAbuse = 'security_abuse';
    case PolicyViolation = 'policy_violation';
    case Other = 'other';
}
