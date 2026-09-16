<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUsableAdministrator();
    }

    public function view(User $user, Comment $comment): bool
    {
        return $user->isUsableAdministrator();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Comment $comment): bool
    {
        return false;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Comment $comment): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Comment $comment): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Comment $comment): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
