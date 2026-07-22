<?php

namespace App\Repositories\Eloquent;

use App\Models\BankAccount;
use App\Models\User;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BankAccountRepository extends BaseRepository implements BankAccountRepositoryInterface
{
    public function __construct(BankAccount $model)
    {
        parent::__construct($model);
    }

    public function allForUser(User $user): Collection
    {
        return $this->model->newQuery()
            ->where('user_id', $user->id)
            ->orderByDesc('is_primary')
            ->latest('id')
            ->get();
    }

    public function unsetPrimaryForUser(User $user, ?int $exceptId = null): void
    {
        $query = $this->model->newQuery()->where('user_id', $user->id)->where('is_primary', true);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_primary' => false]);
    }

    public function existsForUser(User $user, string $accountNumberHash): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $user->id)
            ->where('account_number_hash', $accountNumberHash)
            ->exists();
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with('user:id,name,phone,upi_handle');

        if (array_key_exists('is_verified', $filters) && $filters['is_verified'] !== null) {
            $query->where('is_verified', $filters['is_verified']);
        }

        return $query->latest('id')->paginate($perPage);
    }
}
