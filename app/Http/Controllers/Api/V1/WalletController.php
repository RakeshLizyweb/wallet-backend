<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StatementRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Services\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(protected WalletService $walletService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getForUser($request->user());

        return $this->success(new WalletResource($wallet));
    }

    public function miniStatement(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getForUser($request->user());

        return $this->success(
            WalletTransactionResource::collection($this->walletService->miniStatement($wallet))
        );
    }

    public function fullStatement(StatementRequest $request): JsonResponse
    {
        $wallet = $this->walletService->getForUser($request->user());

        $statement = $this->walletService->fullStatement(
            $wallet,
            $request->only(['type', 'category', 'from', 'to']),
            (int) $request->input('per_page', 20)
        );

        return $this->success(WalletTransactionResource::collection($statement));
    }
}
