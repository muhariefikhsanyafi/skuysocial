<?php

namespace App\Jobs;

use App\Enums\OneSignalPushOutcome;
use App\Models\AnnouncementPushBatch;
use App\Services\OneSignalPushService;
use App\Support\AnnouncementAudience;
use App\Support\OneSignalExternalId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DeliverAnnouncementPushBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $batchId) {}

    public function handle(OneSignalPushService $pushes, AnnouncementAudience $audience): void
    {
        $batch = AnnouncementPushBatch::query()->find($this->batchId);
        $announcement = $batch?->announcement;
        if ($batch === null || $announcement === null || $batch->batch_type !== 'delivery' || $batch->status !== 'queued') {
            return;
        }

        $claimed = AnnouncementPushBatch::query()->whereKey($batch->getKey())->where('status', 'queued')->update([
            'status' => 'processing', 'started_at' => now(), 'updated_at' => now(),
        ]);
        if ($claimed !== 1) {
            return;
        }

        try {
            $query = $audience->recipientQuery($announcement)
                ->whereBetween('users.id', [(int) $batch->range_start_user_id, (int) $batch->range_end_user_id]);
            $query = $audience->constrainLocale($query, (string) $batch->locale)->orderBy('users.id');
            $failed = false;
            $sent = false;
            $noSubscribers = false;
            $eligibleCount = 0;

            $query->chunkById(AnnouncementAudience::PUSH_BATCH_SIZE, function ($users) use (
                $audience, $announcement, $batch, $pushes, &$failed, &$sent, &$noSubscribers, &$eligibleCount,
            ): void {
                $safeUsers = $users->filter(static function ($user) use ($audience): bool {
                    if (! $audience->isEligible($user)) {
                        return false;
                    }
                    try {
                        OneSignalExternalId::forUser($user);

                        return true;
                    } catch (\InvalidArgumentException) {
                        return false;
                    }
                })->values();
                if ($safeUsers->isEmpty()) {
                    return;
                }

                $eligibleCount += $safeUsers->count();
                $locale = (string) $batch->locale;
                $title = (string) $announcement->getAttribute('title_'.$locale);
                $excerpt = Str::limit(Str::squish(strip_tags((string) $announcement->getAttribute('body_'.$locale))), 240, '…');
                try {
                    $outcome = $pushes->sendToUsers(
                        $safeUsers,
                        $locale === 'id' ? ['en' => $title, 'id' => $title] : ['en' => $title],
                        $locale === 'id' ? ['en' => $excerpt, 'id' => $excerpt] : ['en' => $excerpt],
                        route('announcements.show', $announcement, true),
                    );
                } catch (Throwable $exception) {
                    $batch->forceFill([
                        'status' => 'failed',
                        'provider_outcome' => 'unknown',
                        'error_class' => $exception::class,
                        'completed_at' => now(),
                    ])->save();
                    Log::warning('Announcement push batch failed safely.', [
                        'announcement_id' => $announcement->getKey(),
                        'batch_id' => $batch->getKey(),
                        'exception_class' => $exception::class,
                    ]);

                    return;
                }

                $failed = $failed || in_array($outcome, [OneSignalPushOutcome::Failed, OneSignalPushOutcome::ConfigurationMissing], true);
                $sent = $sent || $outcome === OneSignalPushOutcome::Sent;
                $noSubscribers = $noSubscribers || $outcome === OneSignalPushOutcome::NoSubscribers;
            }, 'users.id', 'id');

            if ($batch->fresh()->status === 'failed') {
                return;
            }

            $outcome = $failed ? 'failed' : ($sent ? 'sent' : ($noSubscribers || $eligibleCount === 0 ? 'no_subscribers' : 'unknown'));
            $batch->forceFill([
                'status' => in_array($outcome, ['failed', 'unknown'], true) ? 'failed' : 'completed',
                'provider_outcome' => $outcome,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $batch->forceFill([
                'status' => 'failed',
                'provider_outcome' => 'unknown',
                'error_class' => $exception::class,
                'completed_at' => now(),
            ])->save();
            Log::warning('Announcement push batch failed safely.', [
                'announcement_id' => $announcement->getKey(),
                'batch_id' => $batch->getKey(),
                'exception_class' => $exception::class,
            ]);
        }
    }
}
