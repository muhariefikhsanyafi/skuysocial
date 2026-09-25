<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Pertahankan perilaku lama: membuka Activity membaca semua notifikasi yang belum dibaca
        $user->unreadNotifications()->update(['read_at' => now()]);

        // Muat setelahnya agar state yang dirender sesuai dengan state tersimpan.
        $notifications = $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(20);

        return view('activity', compact('notifications'));
    }
}
