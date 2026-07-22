<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByPhone(string $phone): ?User
    {
        return $this->model->newQuery()->where('phone', $phone)->first();
    }

    public function findByUpiHandle(string $upiHandle): ?User
    {
        return $this->model->newQuery()->where('upi_handle', $upiHandle)->first();
    }

    public function findByUsername(string $username): ?User
    {
        return $this->model->newQuery()->where('username', $username)->first();
    }

    public function generateUniqueUpiHandle(string $phone): string
    {
        $domain = config('wallet.qr.domain', 'wallet');
        $handle = "{$phone}@{$domain}";
        $suffix = 1;

        while ($this->model->newQuery()->where('upi_handle', $handle)->exists()) {
            $handle = "{$phone}{$suffix}@{$domain}";
            $suffix++;
        }

        return $handle;
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('upi_handle', 'like', "%{$search}%");
            });
        }

        return $query->latest('id')->paginate($perPage);
    }
}
