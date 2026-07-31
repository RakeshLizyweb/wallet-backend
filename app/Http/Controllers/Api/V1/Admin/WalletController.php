<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\LedgerType;
use App\Enums\OtpPurpose;
use App\Enums\WalletStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WalletAdjustRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use App\Services\AccountService;
use App\Services\OtpService;
use App\Services\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WalletService $walletService,
        protected AccountService $accountService,
        protected OtpService $otpService,
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

    /**
     * Sends an OTP to the wallet owner's phone so they can consent to an
     * admin-initiated debit — the admin reads it back from the user and
     * enters it in the adjust form to confirm.
     */
    public function sendAdjustOtp(Wallet $wallet, Request $request): JsonResponse
    {
        $otp = $this->otpService->generate($wallet->user->phone, OtpPurpose::AdminDebitConfirmation, $request->ip());

        return $this->success(['debug_otp' => $otp->plain_code ?? null], "OTP sent to the user's phone.");
    }

    public function adjust(WalletAdjustRequest $request, Wallet $wallet): JsonResponse
    {
        $bucket = $request->input('bucket', 'account');
        $type = LedgerType::from($request->type);

        if ($type === LedgerType::Debit) {
            if ($request->boolean('force')) {
                if (! Hash::check((string) $request->admin_password, (string) $request->user()->password)) {
                    throw new ApiException('Invalid admin password.', 401);
                }
            } else {
                $this->otpService->verify($wallet->user->phone, OtpPurpose::AdminDebitConfirmation, $request->otp);
            }
        }

        if ($bucket === 'wallet') {
            $this->walletService->adjust($wallet, (float) $request->amount, $type, $request->reason);
        } else {
            $account = $this->accountService->getForUser($wallet->user);
            $this->accountService->adjust($account, (float) $request->amount, $type, $request->reason);
        }

        return $this->success(new WalletResource($wallet->fresh()->load('user.account')), 'Balance adjusted.');
    }
}
