<?php

namespace App\Actions;

final readonly class DailyCheckInResult
{
    public function __construct(
        public string $date,
        public bool $checkedIn,
        public bool $claimedNow,
        public int $reward,
        public int $awarded,
        public int $balance,
    ) {
    }
}
