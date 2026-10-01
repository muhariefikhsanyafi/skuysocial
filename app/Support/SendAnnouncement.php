<?php

namespace App\Support;

use App\Jobs\DispatchAnnouncementPush;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendAnnouncement
{
    public function __construct(private readonly AnnouncementAudience $audience) {}

    public function handle(Announcement $announcement, User $actor): bool
    {
        if (! $actor->isUsableAdministrator()) {
            abort(403);
        }

        $dispatchBatchId = DB::transaction(function () use ($announcement, $actor): ?int {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->getKey());
            if ($locked->status === Announcement::STATUS_SENT) {
                return null;
            }
            $this->validateCopy($locked);
            if (! in_array($locked->audience_type, [Announcement::AUDIENCE_ALL, Announcement::AUDIENCE_TARGETED], true)) {
                throw ValidationException::withMessages(['audience_type' => __('announcements.validation.audience')]);
            }

            if ($locked->audience_type === Announcement::AUDIENCE_ALL) {
                $locked->audience_cutoff_user_id = (int) (User::query()->max('id') ?? 0);
            } else {
                $targets = $locked->targets()->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
                try {
                    $this->audience->freezeDraft($locked, $targets);
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages(['audience' => __('announcements.validation.targets')]);
                }
            }

            if ($this->audience->draftRecipientQuery($locked)->count() < 1) {
                throw ValidationException::withMessages(['audience' => __('announcements.validation.no_recipients')]);
            }

            $locked->forceFill([
                'status' => Announcement::STATUS_SENT,
                'sent_by' => $actor->getKey(),
                'sent_at' => now(),
            ])->save();
            $dispatch = $this->audience->createDeliveryBatches($locked);

            return $dispatch > 0
                ? (int) $locked->pushBatches()->where('batch_type', 'dispatch')->value('id')
                : null;
        });

        if ($dispatchBatchId === null) {
            return false;
        }

        DB::afterCommit(static function () use ($announcement, $dispatchBatchId): void {
            DispatchAnnouncementPush::dispatch((int) $announcement->getKey(), $dispatchBatchId)
                ->onConnection(config('queue.default'))
                ->afterCommit();
        });

        return true;
    }

    private function validateCopy(Announcement $announcement): void
    {
        foreach (['title_id', 'title_en', 'body_id', 'body_en'] as $field) {
            $value = $announcement->getAttribute($field);
            $length = is_string($value) ? mb_strlen(trim($value)) : 0;
            $limit = str_starts_with($field, 'title_') ? 160 : 5000;
            if ($length < 1 || $length > $limit) {
                throw ValidationException::withMessages([$field => __('announcements.validation.copy')]);
            }
        }
    }
}
