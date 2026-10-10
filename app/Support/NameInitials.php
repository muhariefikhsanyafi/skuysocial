<?php

namespace App\Support;

final class NameInitials
{
    public static function from(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_substr($part, 0, 1);
        }

        return $initials === '' ? '?' : mb_strtoupper($initials);
    }
}
