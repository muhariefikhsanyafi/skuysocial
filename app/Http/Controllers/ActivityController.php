<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Ambil semua notifikasi milik user ini
        $notifications = $user->notifications;

        // Tandai semua notifikasi yang belum dibaca menjadi "sudah dibaca" ketika halaman ini dibuka
        $user->unreadNotifications->markAsRead();

        return view('activity', compact('notifications'));
    }
}
