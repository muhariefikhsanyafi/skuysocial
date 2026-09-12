<?php

namespace App\Enums;

enum ModerationState: string
{
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Banned = 'banned';

    public function allowsAuthentication(): bool
    {
        return match ($this) {
            self::Active, self::Restricted => true,
            self::Suspended, self::Banned => false,
        };
    }

    public function allowsAuthenticatedSession(): bool
    {
        return $this->allowsAuthentication();
    }

    public function allowsSocialWrites(): bool
    {
        return $this === self::Active;
    }

    public function revokesAuthenticatedAccess(): bool
    {
        return ! $this->allowsAuthenticatedSession();
    }
}
