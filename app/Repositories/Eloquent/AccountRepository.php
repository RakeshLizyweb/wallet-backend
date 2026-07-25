<?php

namespace App\Repositories\Eloquent;

use App\Models\Account;
use App\Models\User;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountRepository extends BaseRepository implements AccountRepositoryInterface
{
    public function __construct(Account $model)
    {
        parent::__construct($model);
    }

    public function findByUser(User $user): ?Account
    {
        return $this->model->newQuery()->where('user_id', $user->id)->first();
    }

    public function generateUniqueAccountNumber(): string
    {
        do {
            $number = 'ACC'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        } while ($this->model->newQuery()->where('account_number', $number)->exists());

        return $number;
    }

    public function lockForUpdate(int $accountId): Account
    {
        return $this->model->newQuery()->lockForUpdate()->findOrFail($accountId);
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with([
            'user:id,name,phone,upi_handle,nationality',
            'user.latestIdentityVerification',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('account_number', 'like', "%{$search}%");
        }

        return $query->latest('id')->paginate($perPage);
    }
}
