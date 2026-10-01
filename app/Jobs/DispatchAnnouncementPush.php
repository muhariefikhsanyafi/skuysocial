<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\AnnouncementPushBatch;
use App\Support\AnnouncementAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchAnnouncementPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $announcementId, public readonly int $dispatchBatchId) {}

    public function handle(AnnouncementAudience $audience): void
    {
        $announcement = Announcement::query()->find($this->announcementId);
        $dispatch = AnnouncementPushBatch::query()->whereKey($this->dispatchBatchId)
            ->where('announcement_id', $this->announcementId)
            ->where('batch_type', 'dispatch')->first();
        if ($announcement === null || $dispatch === null || $dispatch->batch_type !== 'dispatch' || $dispatch->status !== 'queued') {
            return;
        }

        $claimed = AnnouncementPushBatch::query()->whereKey($dispatch->getKey())->where('status', 'queued')->update([
            'status' => 'processing', 'started_at' => now(), 'updated_at' => now(),
        ]);
        if ($claimed !== 1) {
            return;
        }

        try {
            $hasPersistedDeliveryBatches = $announcement->pushBatches()
                ->where('batch_type', 'delivery')
                ->exists();
            if (! $hasPersistedDeliveryBatches) {
                DB::transaction(static fn (): int => $audience->createDeliveryBatchRows($announcement));
            }

            $deliveryCount = $announcement->pushBatches()
                ->where('batch_type', 'delivery')
                ->count();
            if ($deliveryCount === 0) {
                $dispatch->forceFill(['status' => 'completed', 'provider_outcome' => 'scheduled', 'completed_at' => now()])->save();

                return;
            }
            $query = DB::table('announcement_push_batches')
                ->select('id')
                ->where('announcement_id', $announcement->getKey())
                ->where('batch_type', 'delivery')
                ->where('status', 'queued')
                ->orderBy('id');
            $query->chunkById(100, static function ($batches): void {
                foreach ($batches as $delivery) {
                    DeliverAnnouncementPushBatch::dispatch((int) $delivery->id)
                        ->onConnection(config('queue.default'))
                        ->afterCommit();
                }
            }, 'id');

            $dispatch->forceFill(['status' => 'completed', 'provider_outcome' => 'scheduled', 'completed_at' => now()])->save();
        } catch (Throwable $exception) {
            $dispatch->forceFill([
                'status' => 'failed',
                'provider_outcome' => 'unknown',
                'error_class' => $exception::class,
                'completed_at' => now(),
            ])->save();
            Log::warning('Announcement dispatch failed safely.', [
                'announcement_id' => $announcement->getKey(),
                'batch_id' => $dispatch->getKey(),
                'exception_class' => $exception::class,
            ]);
        }
    }
}
