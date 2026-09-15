<?php

namespace App\Actions;

use App\Enums\ModerationReason;
use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Models\ModerationEvent;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OverflowException;

class TransitionUserModerationState
{
    public const STALE_STATE_ERROR_CODE = 409;

    public function handle(
        User $actor,
        User $target,
        ModerationState $newState,
        ModerationReason $reasonCode,
        string $reasonText,
        ?ModerationState $expectedPreviousState = null,
        ?int $expectedPreviousVersion = null,
    ): ModerationEvent {
        $reasonText = trim($reasonText);

        if ($reasonText === '') {
            throw new InvalidArgumentException('A moderation reason is required.');
        }

        if (mb_strlen($reasonText) > 2000) {
            throw new InvalidArgumentException('The moderation reason is too long.');
        }

        if ($expectedPreviousVersion !== null && $expectedPreviousVersion < 0) {
            throw new InvalidArgumentException('The expected moderation state version is invalid.');
        }

        return DB::transaction(function () use (
            $actor,
            $target,
            $newState,
            $reasonCode,
            $reasonText,
            $expectedPreviousState,
            $expectedPreviousVersion,
        ): ModerationEvent {
            $usableAdministrators = User::query()
                ->where('role', UserRole::Administrator->value)
                ->whereNotNull('email_verified_at')
                ->where('moderation_state', ModerationState::Active->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lockedActor = $usableAdministrators->first(
                fn (User $candidate): bool => $candidate->getKey() === $actor->getKey(),
            );

            if (! $lockedActor instanceof User) {
                throw new AuthorizationException('Only usable administrators may moderate users.');
            }

            $lockedTarget = $usableAdministrators->first(
                fn (User $candidate): bool => $candidate->getKey() === $target->getKey(),
            ) ?? User::query()->lockForUpdate()->findOrFail($target->getKey());

            $previousRawState = $lockedTarget->getAttribute('moderation_state');
            $previousState = is_string($previousRawState)
                ? ModerationState::tryFrom($previousRawState)
                : null;

            if (! $previousState instanceof ModerationState) {
                throw new DomainException('The stored moderation state is invalid.');
            }

            $previousVersion = $lockedTarget->moderationStateVersion();

            if ($previousVersion === null) {
                throw new DomainException('The stored moderation state version is invalid.');
            }

            if (
                (
                    $expectedPreviousState instanceof ModerationState
                    && $previousState !== $expectedPreviousState
                )
                || (
                    $expectedPreviousVersion !== null
                    && $previousVersion !== $expectedPreviousVersion
                )
            ) {
                throw new DomainException(
                    'The target moderation state changed before this transition was applied.',
                    self::STALE_STATE_ERROR_CODE,
                );
            }

            if ($previousState === $newState) {
                throw new DomainException('The moderation state is unchanged.');
            }

            if (
                $lockedTarget->isUsableAdministrator()
                && $newState !== ModerationState::Active
                && $usableAdministrators->count() <= 1
            ) {
                throw new AuthorizationException('At least one usable administrator must remain.');
            }

            if ($lockedActor->is($lockedTarget)) {
                throw new AuthorizationException('Administrators may not moderate their own account.');
            }

            $sessionVersion = $lockedTarget->authSessionVersion();

            if ($sessionVersion === null) {
                throw new DomainException('The stored authentication session version is invalid.');
            }

            if ($previousVersion === PHP_INT_MAX) {
                throw new OverflowException('The moderation state version cannot be incremented.');
            }

            $changedAt = now();
            $updates = [
                'moderation_state' => $newState->value,
                'moderation_state_changed_at' => $changedAt,
                'moderation_state_version' => $previousVersion + 1,
            ];

            if ($newState->revokesAuthenticatedAccess()) {
                if ($sessionVersion === PHP_INT_MAX) {
                    throw new OverflowException('The authentication session version cannot be incremented.');
                }

                $updates['auth_session_version'] = $sessionVersion + 1;
                $updates['remember_token'] = Str::random(60);
            }

            $lockedTarget->forceFill($updates)->save();

            return ModerationEvent::query()->create([
                'target_user_id' => $lockedTarget->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'previous_state' => $previousState->value,
                'new_state' => $newState->value,
                'reason_code' => $reasonCode->value,
                'reason_text' => $reasonText,
                'created_at' => $changedAt,
            ]);
        });
    }
}
