<?php

namespace App\Policies;

use App\Models\ModerationEvent;
use App\Models\User;

class ModerationEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUsableAdministrator();
    }

    public function view(User $user, ModerationEvent $event): bool
    {
        return $user->isUsableAdministrator();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ModerationEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, ModerationEvent $event): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, ModerationEvent $event): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, ModerationEvent $event): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, ModerationEvent $event): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function associate(User $user, ModerationEvent $event): bool
    {
        return false;
    }

    public function dissociate(User $user, ModerationEvent $event): bool
    {
        return false;
    }

    public function attach(User $user, ModerationEvent $event): bool
    {
        return false;
    }

    public function detach(User $user, ModerationEvent $event): bool
    {
        return false;
    }
}
