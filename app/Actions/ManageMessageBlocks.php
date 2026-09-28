<?php

namespace App\Actions;

use App\Models\MessageBlock;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class ManageMessageBlocks
{
    public function block(User $blocker, User $blocked): void
    {
        if ($blocker->is($blocked)) {
            throw ValidationException::withMessages([
                'blocked_id' => __('messages.self_block_forbidden'),
            ]);
        }

        $pair = [
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $blocked->getKey(),
        ];

        try {
            MessageBlock::query()->firstOrCreate($pair);
        } catch (UniqueConstraintViolationException $exception) {
            if (! MessageBlock::query()->where($pair)->exists()) {
                throw $exception;
            }
        }
    }

    public function unblock(User $blocker, User $blocked): void
    {
        if ($blocker->is($blocked)) {
            return;
        }

        MessageBlock::query()
            ->where('blocker_id', $blocker->getKey())
            ->where('blocked_id', $blocked->getKey())
            ->delete();
    }
}
