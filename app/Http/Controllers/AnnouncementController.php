<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Services\LocalePreference;
use App\Support\AnnouncementAudience;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request, AnnouncementAudience $audience, LocalePreference $locales): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $audience->isEligible($user), 403);
        $locale = $locales->supported($user->locale) ?? $locales->configuredLocale();
        $announcements = $audience->visibleQuery($user)
            ->withExists(['reads as read_by_me' => static fn ($query) => $query->where('user_id', $user->getKey())])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->simplePaginate(20);

        return view('announcements.index', compact('announcements', 'locale'));
    }

    public function show(Request $request, Announcement $announcement, AnnouncementAudience $audience, LocalePreference $locales): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $audience->visibleTo($announcement, $user), 404);
        $audience->upsertRead($announcement, $user);
        $locale = $locales->supported($user->locale) ?? $locales->configuredLocale();

        return view('announcements.show', compact('announcement', 'locale'));
    }
}
