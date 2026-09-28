<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        $conversation = $message->conversation;

        return $user->canMaintainAuthenticatedSession()
            && $conversation !== null
            && $conversation->isParticipant($user);
    }
}
