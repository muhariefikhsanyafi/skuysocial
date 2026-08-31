<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostLiked extends Notification
{
    use Queueable;

    public $liker;
    public $post;

    public function __construct($liker, $post)
    {
        $this->liker = $liker;
        $this->post = $post;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->liker->id,
            'username' => $this->liker->username,
            'name' => $this->liker->name,
            'avatar' => $this->liker->avatar,
            'post_slug' => $this->post->getRouteKey(), // Key lama dipertahankan, nilainya adalah uid
            'type' => 'like',
            'message' => 'menyukai utas Anda.'
        ];
    }
}
