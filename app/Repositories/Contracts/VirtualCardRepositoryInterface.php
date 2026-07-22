<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\Models\VirtualCard;

interface VirtualCardRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUser(User $user): ?VirtualCard;
}
