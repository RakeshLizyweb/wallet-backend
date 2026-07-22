<?php

namespace App\Policies;

use App\Models\ScratchCard;
use App\Models\User;

class ScratchCardPolicy
{
    public function scratch(User $user, ScratchCard $scratchCard): bool
    {
        return $user->id === $scratchCard->user_id;
    }

    public function redeem(User $user, ScratchCard $scratchCard): bool
    {
        return $user->id === $scratchCard->user_id;
    }
}
