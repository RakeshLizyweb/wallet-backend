<?php

namespace App\Repositories\Contracts;

use App\Enums\OtpPurpose;
use App\Models\Otp;

interface OtpRepositoryInterface extends BaseRepositoryInterface
{
    public function latestFor(string $phone, OtpPurpose $purpose): ?Otp;

    public function invalidatePrevious(string $phone, OtpPurpose $purpose): void;
}
