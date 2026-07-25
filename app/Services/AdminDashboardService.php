<?php

namespace App\Services;

use App\Models\IdentityVerification;
use App\Models\ScratchCard;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;

class AdminDashboardService
{
    public function stats(): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'verified' => User::where('tier', 'verified')->count(),
                'premium' => User::where('tier', 'premium')->count(),
            ],
            'wallets' => [
                'total_balance' => (float) Wallet::sum('balance'),
                'total_frozen' => (float) Wallet::sum('frozen_balance'),
                'active' => Wallet::where('status', 'active')->count(),
                'frozen' => Wallet::where('status', 'frozen')->count(),
            ],
            'transactions' => [
                'today_count' => Transfer::whereDate('created_at', now()->toDateString())->count(),
                'today_amount' => (float) Transfer::whereDate('created_at', now()->toDateString())
                    ->where('status', 'success')->sum('amount'),
                'total_count' => Transfer::count(),
                'pending' => Transfer::where('status', 'pending')->count(),
            ],
            'fees' => [
                'today' => (float) Transfer::whereDate('created_at', now()->toDateString())
                    ->where('status', 'success')->sum('fee'),
                'total' => (float) Transfer::where('status', 'success')->sum('fee'),
            ],
            'verifications' => [
                'pending' => IdentityVerification::where('status', 'pending')->count(),
                'approved' => IdentityVerification::where('status', 'approved')->count(),
                'rejected' => IdentityVerification::where('status', 'rejected')->count(),
            ],
            'rewards' => [
                'unscratched' => ScratchCard::where('is_scratched', false)->count(),
                'unredeemed' => ScratchCard::where('is_scratched', true)->where('is_redeemed', false)->count(),
            ],
        ];
    }
}
