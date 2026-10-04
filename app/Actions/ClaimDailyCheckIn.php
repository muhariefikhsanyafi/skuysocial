<?php

namespace App\Actions;

use App\Enums\PointTransactionType;
use App\Models\DailyCheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClaimDailyCheckIn
{
    public const REWARD = 10;

    public function __construct(private readonly ApplyPointTransaction $points)
    {
    }

    public function status(User $owner): DailyCheckInResult
    {
        $ownerId = $this->ownerId($owner);
        $date = $this->applicationDate();
        $checkedIn = DailyCheckIn::query()
            ->where('user_id', $ownerId)
            ->where('checkin_date', $date)
            ->exists();

        return $this->result($owner, $date, $checkedIn, false, 0);
    }

    public function handle(User $owner): DailyCheckInResult
    {
        $ownerId = $this->ownerId($owner);
        $date = $this->applicationDate();
        // Transaction identities are wallet-scoped; keep the key free of user data.
        $eventKey = 'v1-'.hash('sha256', "daily_checkin\0{$date}");

        try {
            DB::transaction(function () use ($owner, $ownerId, $date, $eventKey): void {
                DailyCheckIn::query()->forceCreate([
                    'user_id' => $ownerId,
                    'checkin_date' => $date,
                ]);

                $this->points->handle(
                    $owner,
                    PointTransactionType::Credit,
                    self::REWARD,
                    'daily_checkin',
                    'daily_checkin',
                    $eventKey,
                    $eventKey,
                );
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $checkInExists = DailyCheckIn::query()
                ->where('user_id', $ownerId)
                ->where('checkin_date', $date)
                ->exists();

            if (! $checkInExists) {
                throw $exception;
            }

            return $this->result($owner, $date, true, false, 0);
        }

        return $this->result($owner, $date, true, true, self::REWARD);
    }

    private function applicationDate(): string
    {
        return CarbonImmutable::now((string) config('app.timezone'))->toDateString();
    }

    private function ownerId(User $owner): int
    {
        $ownerId = filter_var($owner->getKey(), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! $owner->exists || $ownerId === false) {
            throw new InvalidArgumentException('Daily check-ins require a persisted user.');
        }

        return $ownerId;
    }

    private function result(
        User $owner,
        string $date,
        bool $checkedIn,
        bool $claimedNow,
        int $awarded,
    ): DailyCheckInResult {
        $balance = $owner->pointWallet()->value('balance');

        return new DailyCheckInResult(
            date: $date,
            checkedIn: $checkedIn,
            claimedNow: $claimedNow,
            reward: self::REWARD,
            awarded: $awarded,
            balance: $balance === null ? 0 : (int) $balance,
        );
    }
}
