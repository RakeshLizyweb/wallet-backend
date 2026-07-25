<?php

namespace App\Repositories\Eloquent;

use App\Models\Transfer;
use App\Models\User;
use App\Repositories\Contracts\TransferRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TransferRepository extends BaseRepository implements TransferRepositoryInterface
{
    public function __construct(Transfer $model)
    {
        parent::__construct($model);
    }

    public function findByReference(string $referenceNumber): ?Transfer
    {
        return $this->model->newQuery()->where('reference_number', $referenceNumber)->first();
    }

    public function paginateForUser(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->where(function (Builder $q) use ($user) {
            $q->where('sender_user_id', $user->id)->orWhere('receiver_user_id', $user->id);
        });

        $this->applyFilters($query, $filters);

        return $query->latest('id')->paginate($perPage);
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with(['senderUser:id,name,phone,upi_handle', 'receiverUser:id,name,phone,upi_handle']);

        $this->applyFilters($query, $filters);

        return $query->latest('id')->paginate($perPage);
    }

    public function sumAmountBetween(array $filters): float
    {
        $query = $this->model->newQuery()->where('status', 'success');
        $this->applyFilters($query, $filters);

        return (float) $query->sum('amount');
    }

    public function sumFeesBetween(array $filters): float
    {
        $query = $this->model->newQuery()->where('status', 'success');
        $this->applyFilters($query, $filters);

        return (float) $query->sum('fee');
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('senderUser', fn ($u) => $u->where('phone', 'like', "%{$search}%"))
                    ->orWhereHas('receiverUser', fn ($u) => $u->where('phone', 'like', "%{$search}%"));
            });
        }
    }
}
