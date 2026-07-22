<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bank\AddBankAccountRequest;
use App\Http\Requests\Bank\UpdateBankAccountRequest;
use App\Http\Resources\BankAccountResource;
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
        return $this->success(
            BankAccountResource::collection($this->bankAccountService->listForUser($request->user()))
        );
    }

    public function store(AddBankAccountRequest $request): JsonResponse
    {
        $bankAccount = $this->bankAccountService->add($request->user(), $request->validated());

        return $this->success(new BankAccountResource($bankAccount), 'Bank account added successfully.', 201);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('update', $bankAccount);

        $bankAccount = $this->bankAccountService->update($request->user(), $bankAccount, $request->validated());

        return $this->success(new BankAccountResource($bankAccount), 'Bank account updated successfully.');
    }

    public function setPrimary(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('update', $bankAccount);

        $bankAccount = $this->bankAccountService->setPrimary($request->user(), $bankAccount);

        return $this->success(new BankAccountResource($bankAccount), 'Primary bank account updated.');
    }

    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('delete', $bankAccount);

        $this->bankAccountService->delete($request->user(), $bankAccount);

        return $this->success(null, 'Bank account removed successfully.');
    }
}
