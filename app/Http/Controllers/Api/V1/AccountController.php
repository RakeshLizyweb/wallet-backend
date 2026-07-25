<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StatementRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\AccountTransactionResource;
use App\Services\AccountService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use ApiResponse;

    public function __construct(protected AccountService $accountService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $account = $this->accountService->getForUser($request->user());

        return $this->success(new AccountResource($account));
    }

    public function miniStatement(Request $request): JsonResponse
    {
        $account = $this->accountService->getForUser($request->user());

        return $this->success(
            AccountTransactionResource::collection($this->accountService->miniStatement($account))
        );
    }

    public function fullStatement(StatementRequest $request): JsonResponse
    {
        $account = $this->accountService->getForUser($request->user());

        $statement = $this->accountService->fullStatement(
            $account,
            $request->only(['type', 'category', 'from', 'to']),
            (int) $request->input('per_page', 20)
        );

        return $this->success(AccountTransactionResource::collection($statement));
    }
}
