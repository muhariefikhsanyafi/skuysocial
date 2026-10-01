<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUsableAdministrator();
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->isUsableAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isUsableAdministrator();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->isUsableAdministrator() && $announcement->status === Announcement::STATUS_DRAFT;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->isUsableAdministrator() && $announcement->status === Announcement::STATUS_DRAFT;
    }
}
