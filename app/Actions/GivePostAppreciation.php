<?php

namespace App\Actions;

use App\Enums\AppreciationType;
use App\Enums\PointTransactionType;
use App\Enums\PostAppreciationStatus;
use App\Models\Post;
use App\Models\PostAppreciation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GivePostAppreciation
{
    public const COST = 5;

    public function __construct(private readonly ApplyPointTransaction $points)
    {
    }

    public function handle(User $sender, Post $post, AppreciationType $type): PostAppreciationResult
    {
        $senderId = filter_var($sender->getKey(), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! $sender->exists || $senderId === false || ! $post->exists || ! is_string($post->uid)) {
            throw new InvalidArgumentException('Post appreciation requires a persisted sender and target post.');
        }

        if ((int) $post->user_id === $senderId) {
            return new PostAppreciationResult(PostAppreciationStatus::SelfAppreciationForbidden);
        }

        $existing = $this->existingAppreciation($senderId, (int) $post->getKey());

        if ($existing !== null) {
            return new PostAppreciationResult(
                PostAppreciationStatus::AlreadyAppreciated,
                $existing->type,
            );
        }

        // The Points primitive scopes both identities to the sender wallet; the
        // hashed post UID keeps the canonical event stable and non-personal.
        $eventKey = 'pa-v1-'.hash('sha256', "post_appreciation\0{$post->uid}");

        try {
            DB::transaction(function () use ($sender, $senderId, $post, $type, $eventKey): void {
                PostAppreciation::query()->forceCreate([
                    'user_id' => $senderId,
                    'post_id' => $post->getKey(),
                    'type' => $type,
                    'points_spent' => self::COST,
                ]);

                $this->points->handle(
                    $sender,
                    PointTransactionType::Debit,
                    self::COST,
                    'post_appreciation',
                    'post_appreciation',
                    $eventKey,
                    $eventKey,
                );
            }, attempts: 5);
        } catch (InsufficientPointBalance) {
            return new PostAppreciationResult(PostAppreciationStatus::InsufficientPoints);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existingAppreciation($senderId, (int) $post->getKey());

            if ($existing === null) {
                throw $exception;
            }

            return new PostAppreciationResult(
                PostAppreciationStatus::AlreadyAppreciated,
                $existing->type,
            );
        }

        return new PostAppreciationResult(PostAppreciationStatus::Appreciated, $type);
    }

    private function existingAppreciation(int $senderId, int $postId): ?PostAppreciation
    {
        return PostAppreciation::query()
            ->where('user_id', $senderId)
            ->where('post_id', $postId)
            ->first();
    }
}
