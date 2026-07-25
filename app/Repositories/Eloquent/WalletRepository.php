<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\Wallet;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class WalletRepository extends BaseRepository implements WalletRepositoryInterface
{
    public function __construct(Wallet $model)
    {
        parent::__construct($model);
    }

    public function findByUser(User $user): ?Wallet
    {
        return $this->model->newQuery()->where('user_id', $user->id)->first();
    }

    public function findByWalletNumber(string $walletNumber): ?Wallet
    {
        return $this->model->newQuery()->where('wallet_number', $walletNumber)->first();
    }

    public function generateUniqueWalletNumber(): string
    {
        do {
            $number = 'WAL'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        } while ($this->model->newQuery()->where('wallet_number', $number)->exists());

        return $number;
    }

    public function lockForUpdate(int $walletId): Wallet
    {
        return $this->model->newQuery()->lockForUpdate()->findOrFail($walletId);
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with([
            'user:id,name,phone,upi_handle,nationality',
            'user.latestIdentityVerification',
            'user.account',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('wallet_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('phone', 'like', "%{$search}%"));
            });
        }

        return $query->latest('id')->paginate($perPage);
    }
}
