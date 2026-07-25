<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\LedgerType;
use App\Enums\WalletStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WalletAdjustRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use App\Services\AccountService;
use App\Services\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WalletService $walletService,
        protected AccountService $accountService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            WalletResource::collection($this->walletService->paginateAll(
                $request->only(['status', 'search']),
                (int) $request->input('per_page', 20)
            ))
        );
    }

    public function freeze(Wallet $wallet): JsonResponse
    {
        return $this->success(new WalletResource($this->walletService->setStatus($wallet, WalletStatus::Frozen)), 'Wallet frozen.');
    }

    public function unfreeze(Wallet $wallet): JsonResponse
    {
        return $this->success(new WalletResource($this->walletService->setStatus($wallet, WalletStatus::Active)), 'Wallet unfrozen.');
    }

    public function adjust(WalletAdjustRequest $request, Wallet $wallet): JsonResponse
    {
        $bucket = $request->input('bucket', 'account');

        if ($bucket === 'wallet') {
            $this->walletService->adjust($wallet, (float) $request->amount, LedgerType::from($request->type), $request->reason);
        } else {
            $account = $this->accountService->getForUser($wallet->user);
            $this->accountService->adjust($account, (float) $request->amount, LedgerType::from($request->type), $request->reason);
        }

        return $this->success(new WalletResource($wallet->fresh()->load('user.account')), 'Balance adjusted.');
    }
}
