<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AccountToAccountRequest;
use App\Http\Requests\Transaction\AccountToWalletRequest;
use App\Http\Requests\Transaction\BankToWalletRequest;
use App\Http\Requests\Transaction\TransferHistoryRequest;
use App\Http\Requests\Transaction\WalletToBankRequest;
use App\Http\Requests\Transaction\WalletToWalletRequest;
use App\Http\Resources\TransferResource;
use App\Models\BankAccount;
use App\Services\TransferService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    use ApiResponse;

    public function __construct(protected TransferService $transferService)
    {
    }

    public function walletToWallet(WalletToWalletRequest $request): JsonResponse
    {
        $transfer = $this->transferService->walletToWallet(
            $request->user(),
            $request->receiver,
            (float) $request->amount,
            $request->pin,
            $request->input('note')
        );

        return $this->success(new TransferResource($transfer), 'Money sent successfully.', 201);
    }

    public function accountToAccount(AccountToAccountRequest $request): JsonResponse
    {
        $transfer = $this->transferService->accountToAccount(
            $request->user(),
            $request->receiver,
            (float) $request->amount,
            $request->pin,
            $request->input('note')
        );

        return $this->success(new TransferResource($transfer), 'Money sent successfully.', 201);
    }

    public function accountToWallet(AccountToWalletRequest $request): JsonResponse
    {
        $transfer = $this->transferService->accountToWallet(
            $request->user(),
            (float) $request->amount,
            $request->pin,
            $request->input('note')
        );

        return $this->success(new TransferResource($transfer), 'Moved to wallet successfully.', 201);
    }

    public function walletToBank(WalletToBankRequest $request): JsonResponse
    {
        $bankAccount = BankAccount::findOrFail($request->bank_account_id);

        $transfer = $this->transferService->walletToBank(
            $request->user(),
            $bankAccount,
            (float) $request->amount,
            $request->pin,
            $request->input('note')
        );

        return $this->success(new TransferResource($transfer), 'Withdrawal to bank successful.', 201);
    }

    public function bankToWallet(BankToWalletRequest $request): JsonResponse
    {
        $bankAccount = BankAccount::findOrFail($request->bank_account_id);

        $transfer = $this->transferService->bankToWallet(
            $request->user(),
            $bankAccount,
            (float) $request->amount,
            $request->input('note')
        );

        return $this->success(new TransferResource($transfer), 'Deposit to wallet successful.', 201);
    }

    public function index(TransferHistoryRequest $request): JsonResponse
    {
        $history = $this->transferService->historyForUser(
            $request->user(),
            $request->only(['type', 'status', 'from', 'to']),
            (int) $request->input('per_page', 20)
        );

        return $this->success(TransferResource::collection($history));
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $transfer = $this->transferService->findByReference($reference);

        if ($transfer->sender_user_id !== $request->user()->id && $transfer->receiver_user_id !== $request->user()->id) {
            return $this->error('Transaction not found.', 404);
        }

        return $this->success(new TransferResource($transfer));
    }
}
