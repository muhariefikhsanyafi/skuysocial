<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommentPosted extends Notification
{
    use Queueable;

    public $commenter;

    public $post;

    public $comment;

    public function __construct($commenter, $post, $comment = null)
    {
        $this->commenter = $commenter;
        $this->post = $post;
        $this->comment = $comment;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->comment?->loadMissing('parent');
        $isReply = $this->comment !== null
            && $this->comment->parent_id !== null
            && (int) $this->comment->parent?->user_id === (int) $notifiable->getKey();

        return array_filter([
            'user_id' => $this->commenter->id,
            'username' => $this->commenter->username,
            'name' => $this->commenter->name,
            'avatar' => $this->commenter->avatar,
            'post_slug' => $this->post->getRouteKey(),

            'type' => $isReply ? 'reply' : 'comment',
            'comment_id' => $this->comment?->getKey(),
            'parent_id' => $this->comment?->parent_id,
            'message' => $isReply
                ? __('activity.messages.reply')
                : __('activity.messages.comment'),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
