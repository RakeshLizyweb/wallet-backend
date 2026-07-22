<?php

namespace App\Repositories\Eloquent;

use App\Models\IdentityVerification;
use App\Models\User;
use App\Repositories\Contracts\IdentityVerificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class IdentityVerificationRepository extends BaseRepository implements IdentityVerificationRepositoryInterface
{
    public function __construct(IdentityVerification $model)
    {
        parent::__construct($model);
    }

    public function latestForUser(User $user): ?IdentityVerification
    {
        return $this->model->newQuery()->where('user_id', $user->id)->latest('id')->first();
    }

    public function historyForUser(User $user): Collection
    {
        return $this->model->newQuery()->where('user_id', $user->id)->latest('id')->get();
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with('user:id,name,phone,upi_handle');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('id')->paginate($perPage);
    }
}
