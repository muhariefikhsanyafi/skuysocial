<?php

namespace App\Policies;

use App\Enums\ModerationState;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isVerifiedAdministrator($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->isVerifiedAdministrator($user);
    }

    public function startDirectConversation(User $sender, User $recipient): bool
    {
        return $sender->canPerformSocialWrites()
            && $sender->isNot($recipient)
            && $recipient->canMaintainAuthenticatedSession();
    }

    public function moderate(User $actor, User $target): bool
    {
        return $actor->isUsableAdministrator()
            && ! $actor->is($target)
            && $target->moderationState() instanceof ModerationState;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, User $model): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function isVerifiedAdministrator(User $user): bool
    {
        return $user->isUsableAdministrator();
    }
}
