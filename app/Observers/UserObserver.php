<?php

namespace App\Observers;

use App\Models\User;
use App\Services\WalletService;

class UserObserver
{
    public function created(User $user): void
    {
        app(WalletService::class)->createForUser($user);
    }
}
