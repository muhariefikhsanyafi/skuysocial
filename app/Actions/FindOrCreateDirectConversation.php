<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FindOrCreateDirectConversation
{
    public function handle(User $first, User $second): Conversation
    {
        $firstId = $this->persistedUserId($first);
        $secondId = $this->persistedUserId($second);

        if ($firstId === $secondId) {
            throw new InvalidArgumentException('A direct conversation requires two distinct users.');
        }

        $participantIds = [$firstId, $secondId];
        sort($participantIds, SORT_NUMERIC);
        $directKey = sprintf('direct:%d:%d', $participantIds[0], $participantIds[1]);

        $existing = Conversation::query()
            ->direct()
            ->where('direct_key', $directKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($directKey, $participantIds): Conversation {
                $conversation = Conversation::query()->create([
                    'type' => Conversation::TYPE_DIRECT,
                    'direct_key' => $directKey,
                ]);

                $conversation->participants()->createMany([
                    ['user_id' => $participantIds[0]],
                    ['user_id' => $participantIds[1]],
                ]);

                return $conversation;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Conversation::query()
                ->direct()
                ->where('direct_key', $directKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function persistedUserId(User $user): int
    {
        $id = filter_var($user->getKey(), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! $user->exists || $id === false) {
            throw new InvalidArgumentException('Direct conversations require persisted users.');
        }

        return $id;
    }
}
