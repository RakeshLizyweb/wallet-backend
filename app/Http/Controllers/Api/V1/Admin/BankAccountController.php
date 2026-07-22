<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminBankAccountResource;
use App\Models\BankAccount;
use App\Services\BankAccountService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    use ApiResponse;

    public function __construct(protected BankAccountService $bankAccountService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->has('is_verified')
            ? ['is_verified' => $request->boolean('is_verified')]
            : [];

        return $this->success(
            AdminBankAccountResource::collection($this->bankAccountService->paginateAll(
                $filters,
                (int) $request->input('per_page', 20)
            ))
        );
    }

    public function verify(BankAccount $bankAccount): JsonResponse
    {
        return $this->success(
            new AdminBankAccountResource($this->bankAccountService->verify($bankAccount)),
            'Bank account verified.'
        );
    }
}
