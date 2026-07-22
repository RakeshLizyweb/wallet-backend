<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\VirtualCard;
use App\Repositories\Contracts\VirtualCardRepositoryInterface;

class VirtualCardRepository extends BaseRepository implements VirtualCardRepositoryInterface
{
    public function __construct(VirtualCard $model)
    {
        parent::__construct($model);
    }

    public function findByUser(User $user): ?VirtualCard
    {
        return $this->model->newQuery()->where('user_id', $user->id)->first();
    }
}
