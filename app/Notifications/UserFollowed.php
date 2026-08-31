<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserFollowed extends Notification
{
    use Queueable;

    public $follower;

    public function __construct($follower)
    {
        $this->follower = $follower;
    }

    public function via(object $notifiable): array
    {
        return ['database']; // Arahkan penyimpanan ke tabel database
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->follower->id,
            'username' => $this->follower->username,
            'name' => $this->follower->name,
            'avatar' => $this->follower->avatar,
            'type' => 'follow',
            'message' => 'mulai mengikuti Anda.'
        ];
    }
}