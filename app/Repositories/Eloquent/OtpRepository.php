<?php

namespace App\Repositories\Eloquent;

use App\Enums\OtpPurpose;
use App\Models\Otp;
use App\Repositories\Contracts\OtpRepositoryInterface;

class OtpRepository extends BaseRepository implements OtpRepositoryInterface
{
    public function __construct(Otp $model)
    {
        parent::__construct($model);
    }

    public function latestFor(string $phone, OtpPurpose $purpose): ?Otp
    {
        return $this->model->newQuery()
            ->where('phone', $phone)
            ->where('purpose', $purpose->value)
            ->latest('id')
            ->first();
    }

    public function invalidatePrevious(string $phone, OtpPurpose $purpose): void
    {
        $this->model->newQuery()
            ->where('phone', $phone)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
