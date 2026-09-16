<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUsableAdministrator();
    }

    public function view(User $user, Post $post): bool
    {
        return $user->isUsableAdministrator();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Post $post): bool
    {
        return false;
    }

    public function delete(User $user, Post $post): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Post $post): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Post $post): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
