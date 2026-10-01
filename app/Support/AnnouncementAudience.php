<?php

namespace App\Support;

use App\Enums\ModerationState;
use App\Models\Announcement;
use App\Models\AnnouncementPushBatch;
use App\Models\User;
use App\Services\LocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AnnouncementAudience
{
    public const int PUSH_BATCH_SIZE = 500;

    public function __construct(private readonly LocalePreference $locales) {}

    public function eligibleUserQuery(): Builder
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->whereIn('moderation_state', [ModerationState::Active->value, ModerationState::Restricted->value])
            ->whereRaw('(users.locale IS NULL OR users.locale IN (?, ?))', ['id', 'en']);
    }

    public function recipientQuery(Announcement $announcement): Builder
    {
        if ($announcement->status !== Announcement::STATUS_SENT) {
            return User::query()->whereRaw('1 = 0');
        }
        $query = $this->eligibleUserQuery();
        if ($announcement->audience_type === Announcement::AUDIENCE_ALL) {
            return $query->where('users.id', '<=', (int) $announcement->audience_cutoff_user_id);
        }
        if ($announcement->audience_type !== Announcement::AUDIENCE_TARGETED) {
            return User::query()->whereRaw('1 = 0');
        }

        return $query->whereExists(static function ($subquery) use ($announcement): void {
            $subquery->selectRaw('1')->from('announcement_targets')
                ->whereColumn('announcement_targets.user_id', 'users.id')
                ->where('announcement_targets.announcement_id', $announcement->getKey());
        });
    }

    public function draftRecipientQuery(Announcement $announcement): Builder
    {
        $query = $this->eligibleUserQuery();
        if ($announcement->audience_type === Announcement::AUDIENCE_ALL) {
            return $query;
        }

        return $query->whereExists(static function ($subquery) use ($announcement): void {
            $subquery->selectRaw('1')->from('announcement_targets')
                ->whereColumn('announcement_targets.user_id', 'users.id')
                ->where('announcement_targets.announcement_id', $announcement->getKey());
        });
    }

    public function isEligible(User $user): bool
    {
        return $user->exists
            && filter_var($user->getKey(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false
            && $user->email_verified_at !== null
            && in_array($user->moderationState(), [ModerationState::Active, ModerationState::Restricted], true)
            && ($user->locale === null || $this->locales->supported($user->locale) !== null)
            && User::query()->whereKey($user->getKey())->exists();
    }

    public function resolveLocale(User $user): string
    {
        return $this->locales->supported($user->locale) ?? $this->locales->configuredLocale();
    }

    public function constrainLocale(Builder $query, string $locale): Builder
    {
        return $query->whereRaw(
            'CASE WHEN users.locale IN (?, ?) THEN users.locale ELSE ? END = ?',
            ['id', 'en', $this->locales->configuredLocale(), $locale],
        );
    }

    public function visibleTo(Announcement $announcement, User $user): bool
    {
        if (! $this->isEligible($user) || $announcement->status !== Announcement::STATUS_SENT) {
            return false;
        }
        if ($announcement->audience_type === Announcement::AUDIENCE_ALL) {
            return (int) $user->getKey() <= (int) $announcement->audience_cutoff_user_id;
        }

        return $announcement->audience_type === Announcement::AUDIENCE_TARGETED
            && $announcement->targets()->where('user_id', $user->getKey())->exists();
    }

    public function visibleQuery(User $user): Builder
    {
        if (! $this->isEligible($user)) {
            return Announcement::query()->whereRaw('1 = 0');
        }

        return Announcement::query()->where('status', Announcement::STATUS_SENT)
            ->where(static function (Builder $query) use ($user): void {
                $query->where(static function (Builder $all) use ($user): void {
                    $all->where('audience_type', Announcement::AUDIENCE_ALL)
                        ->where('audience_cutoff_user_id', '>=', $user->getKey());
                })->orWhere(static function (Builder $targeted) use ($user): void {
                    $targeted->where('audience_type', Announcement::AUDIENCE_TARGETED)
                        ->whereExists(static function ($targets) use ($user): void {
                            $targets->selectRaw('1')->from('announcement_targets')
                                ->whereColumn('announcement_targets.announcement_id', 'announcements.id')
                                ->where('announcement_targets.user_id', $user->getKey());
                        });
                });
            });
    }

    public function unreadCount(User $user): int
    {
        if (! $this->isEligible($user)) {
            return 0;
        }

        return $this->visibleQuery($user)->whereNotExists(static function ($query) use ($user): void {
            $query->selectRaw('1')->from('announcement_reads')
                ->whereColumn('announcement_reads.announcement_id', 'announcements.id')
                ->where('announcement_reads.user_id', $user->getKey());
        })->count();
    }

    public function pushStatesFor(iterable $announcements): array
    {
        $ids = collect($announcements)->pluck('id')->all();
        if ($ids === []) {
            return [];
        }
        $states = AnnouncementPushBatch::query()->whereIn('announcement_id', $ids)->where('batch_type', 'delivery')
            ->get(['announcement_id', 'status', 'provider_outcome'])->groupBy('announcement_id');
        $result = [];
        foreach ($ids as $id) {
            $batches = $states->get($id, collect());
            if ($batches->isEmpty() || $batches->contains(static fn ($batch): bool => in_array($batch->status, ['queued', 'processing'], true))) {
                $result[$id] = 'queued';
            } elseif ($batches->contains(static fn ($batch): bool => $batch->provider_outcome === 'unknown')) {
                $result[$id] = 'unknown';
            } elseif ($batches->contains(static fn ($batch): bool => $batch->provider_outcome === 'failed')) {
                $result[$id] = 'failed';
            } elseif ($batches->contains(static fn ($batch): bool => $batch->provider_outcome === 'sent')) {
                $result[$id] = 'sent';
            } else {
                $result[$id] = 'no_subscribers';
            }
        }

        return $result;
    }

    public function upsertRead(Announcement $announcement, User $user): void
    {
        DB::table('announcement_reads')->upsert([[
            'announcement_id' => $announcement->getKey(), 'user_id' => $user->getKey(), 'read_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]], ['announcement_id', 'user_id'], ['read_at', 'updated_at']);
    }

    /** @param list<int> $targetIds */
    public function replaceDraftTargets(Announcement $announcement, array $targetIds): void
    {
        if ($announcement->status !== Announcement::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft targets can be changed.');
        }
        $ids = array_values(array_unique(array_map('intval', $targetIds)));
        $valid = $this->eligibleUserQuery()->whereIn('users.id', $ids)->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();
        if (count($valid) !== count($ids)) {
            throw new InvalidArgumentException('Every targeted user must currently be eligible.');
        }
        $announcement->targets()->delete();
        foreach ($ids as $userId) {
            $announcement->targets()->create(['user_id' => $userId]);
        }
    }

    /** @param list<int> $targetIds */
    public function freezeDraft(Announcement $announcement, array $targetIds): void
    {
        if ($announcement->audience_type !== Announcement::AUDIENCE_TARGETED) {
            return;
        }
        $ids = array_values(array_unique(array_map('intval', $targetIds)));
        if ($ids === []) {
            throw new InvalidArgumentException('Choose at least one eligible user.');
        }
        $valid = $this->eligibleUserQuery()->whereIn('users.id', $ids)->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();
        if (count($valid) !== count($ids)) {
            throw new InvalidArgumentException('Target audience changed; review eligible recipients again.');
        }
        $announcement->targets()->delete();
        foreach ($ids as $userId) {
            $announcement->targets()->create(['user_id' => $userId]);
        }
    }

    public function createDeliveryBatches(Announcement $announcement): int
    {
        $count = $this->recipientQuery($announcement)->count();
        if ($count === 0) {
            return 0;
        }
        $dispatch = $announcement->pushBatches()->create([
            'batch_key' => 'dispatch', 'batch_type' => 'dispatch', 'locale' => 'all',
            'recipient_count' => $count, 'status' => 'queued',
        ]);

        return (int) $dispatch->getKey();
    }

    public function createDeliveryBatchRows(Announcement $announcement): int
    {
        $existingBatchCount = $announcement->pushBatches()->where('batch_type', 'delivery')->count();
        if ($existingBatchCount > 0) {
            return $existingBatchCount;
        }
        $created = 0;
        $baseMembership = $this->membershipQuery($announcement);
        $baseMembership->orderBy('users.id')->chunkById(self::PUSH_BATCH_SIZE, function ($users) use ($announcement, &$created): void {
            $userIds = $users->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $rangeStart = $userIds[0];
            $rangeEnd = $userIds[array_key_last($userIds)];
            foreach (['id', 'en'] as $locale) {
                $localeQuery = $this->constrainLocale(
                    $this->membershipQuery($announcement)->whereBetween('users.id', [$rangeStart, $rangeEnd]),
                    $locale,
                )->orderBy('users.id');
                $localeQuery->chunkById(self::PUSH_BATCH_SIZE, static function ($localeUsers) use ($announcement, $locale, &$created): void {
                    $ids = $localeUsers->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();
                    if ($ids === []) {
                        return;
                    }
                    $created += count($ids);
                    $startId = $ids[0];
                    $endId = $ids[array_key_last($ids)];
                    $announcement->pushBatches()->firstOrCreate([
                        'batch_key' => 'delivery:'.$locale.':'.$startId.':'.$endId,
                    ], [
                        'batch_type' => 'delivery', 'locale' => $locale,
                        'range_start_user_id' => $startId, 'range_end_user_id' => $endId,
                        'recipient_count' => count($ids), 'status' => 'queued',
                    ]);
                }, 'users.id', 'id');
            }
        }, 'users.id', 'id');

        return $created;
    }

    private function membershipQuery(Announcement $announcement): Builder
    {
        $query = User::query();
        if ($announcement->status !== Announcement::STATUS_SENT) {
            return $query->whereRaw('1 = 0');
        }
        $query->whereNotNull('email_verified_at')
            ->whereIn('moderation_state', [ModerationState::Active->value, ModerationState::Restricted->value])
            ->whereRaw('(users.locale IS NULL OR users.locale IN (?, ?))', ['id', 'en']);
        if ($announcement->audience_type === Announcement::AUDIENCE_ALL) {
            return $query->where('users.id', '<=', (int) $announcement->audience_cutoff_user_id);
        }
        if ($announcement->audience_type !== Announcement::AUDIENCE_TARGETED) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(static function ($subquery) use ($announcement): void {
            $subquery->selectRaw('1')->from('announcement_targets')
                ->whereColumn('announcement_targets.user_id', 'users.id')
                ->where('announcement_targets.announcement_id', $announcement->getKey());
        });
    }
}
