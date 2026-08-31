<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommentPosted extends Notification
{
    use Queueable;

    public $commenter;
    public $post;

    public function __construct($commenter, $post)
    {
        $this->commenter = $commenter;
        $this->post = $post;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->commenter->id,
            'username' => $this->commenter->username,
            'name' => $this->commenter->name,
            'avatar' => $this->commenter->avatar,
            'post_slug' => $this->post->getRouteKey(),
            'type' => 'comment',
            'message' => 'membalas utas Anda.'
        ];
    }
}
