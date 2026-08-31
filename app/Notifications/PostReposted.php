<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostReposted extends Notification
{
    use Queueable;

    public $reposter;
    public $post;

    public function __construct($reposter, $post)
    {
        $this->reposter = $reposter;
        $this->post = $post;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->reposter->id,
            'username' => $this->reposter->username,
            'name' => $this->reposter->name,
            'avatar' => $this->reposter->avatar,
            'post_slug' => $this->post->getRouteKey(),
            'type' => 'repost',
            'message' => 'memposting ulang utas Anda.'
        ];
    }
}
