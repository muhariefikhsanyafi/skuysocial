<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->canMaintainAuthenticatedSession()
            && $conversation->isParticipant($user);
    }

    public function viewMessages(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $user->canPerformSocialWrites()
            && $conversation->isParticipant($user);
    }
}
