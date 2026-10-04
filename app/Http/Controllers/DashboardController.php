<?php

namespace App\Http\Controllers;

use App\Actions\ClaimDailyCheckIn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ClaimDailyCheckIn $dailyCheckIn): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('dashboard', [
            'dailyCheckIn' => $dailyCheckIn->status($user),
        ]);
    }
}
