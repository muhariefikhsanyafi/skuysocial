<?php

namespace App\Http\Controllers;

use App\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointWalletController extends Controller
{
    public function index(Request $request): View
    {
        $wallet = $request->user()->pointWallet()->first();
        $transactions = $wallet !== null
            ? $wallet->transactions()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->simplePaginate(20)
            : PointTransaction::query()
                ->where('wallet_id', 0)
                ->simplePaginate(20);

        return view('points.history', [
            'balance' => (int) ($wallet?->balance ?? 0),
            'transactions' => $transactions,
        ]);
    }
}
