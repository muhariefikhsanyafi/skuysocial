<?php

namespace App\Http\Controllers;

use App\Actions\ClaimDailyCheckIn;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyCheckInController extends Controller
{
    public function store(Request $request, ClaimDailyCheckIn $dailyCheckIn): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $result = $dailyCheckIn->handle($user);

        return to_route('dashboard')->with([
            'daily_checkin_claimed' => $result->claimedNow,
            'daily_checkin_awarded' => $result->awarded,
        ]);
    }
}
