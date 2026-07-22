<?php

namespace App\Listeners;

use App\Events\TransferCompleted;
use App\Services\RewardService;

class CreateScratchCardOnTransfer
{
    public function __construct(protected RewardService $rewardService)
    {
    }

    public function handle(TransferCompleted $event): void
    {
        $this->rewardService->createForTransfer($event->transfer);
    }
}
