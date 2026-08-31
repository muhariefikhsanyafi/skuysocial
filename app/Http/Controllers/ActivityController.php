<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Ambil notifikasi secara terbatas dengan urutan yang deterministik
        $notifications = $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(20);

        // Pertahankan perilaku lama: membuka Activity membaca semua notifikasi yang belum dibaca
        $user->unreadNotifications()->update(['read_at' => now()]);

        return view('activity', compact('notifications'));
    }
}
