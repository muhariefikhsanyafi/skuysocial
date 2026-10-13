<?php

namespace App\Http\Controllers;

use App\Models\User;

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

        $actorIds = collect($notifications->items())
            ->map(fn ($notification) => $notification->data['user_id'] ?? null)
            ->filter(fn ($id) => is_numeric($id))
            ->unique()->values();
        $notificationActors = User::query()->whereIn('id', $actorIds)
            ->get(['id', 'avatar'])->keyBy('id');

        return view('activity', compact('notifications', 'notificationActors'));
    }
}
