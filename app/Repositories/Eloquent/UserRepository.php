<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

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

    public function findByReferralCode(string $code): ?User
    {
        return $this->model->newQuery()->where('referral_code', $code)->first();
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

    public function findWithVerification(int $id): User
    {
        return $this->model->newQuery()->with('latestIdentityVerification')->findOrFail($id);
    }

    public function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while ($this->model->newQuery()->where('referral_code', $code)->exists());

        return $code;
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with('latestIdentityVerification');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        if (! empty($filters['nationality'])) {
            $query->where('nationality', $filters['nationality']);
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
