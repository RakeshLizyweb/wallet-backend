<?php

namespace App\Services;

use App\Repositories\Contracts\TransferRepositoryInterface;

class AdminReportService
{
    public function __construct(protected TransferRepositoryInterface $transfers)
    {
    }

    public function transactionsSummary(array $filters): array
    {
        $byType = [];

        foreach (['wallet_to_wallet', 'wallet_to_bank', 'bank_to_wallet'] as $type) {
            $byType[$type] = $this->transfers->sumAmountBetween(array_merge($filters, ['type' => $type]));
        }

        return [
            'total_volume' => $this->transfers->sumAmountBetween($filters),
            'total_fees_collected' => $this->transfers->sumFeesBetween($filters),
            'volume_by_type' => $byType,
        ];
    }
}
