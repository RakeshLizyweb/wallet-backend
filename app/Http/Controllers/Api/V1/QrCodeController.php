<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Qr\ValidateQrRequest;
use App\Services\QrCodeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    use ApiResponse;

    public function __construct(protected QrCodeService $qrCodeService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'upi_handle' => $user->upi_handle,
            'wallet_number' => $user->wallet?->wallet_number,
            'qr_image' => $this->qrCodeService->generateDataUri($user),
        ]);
    }

    public function download(Request $request): Response
    {
        $png = $this->qrCodeService->generatePng($request->user());

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="wallet-qr.png"',
        ]);
    }

    public function validateQr(ValidateQrRequest $request): JsonResponse
    {
        $receiver = $this->qrCodeService->validate($request->payload);

        return $this->success($receiver, 'QR code is valid.');
    }
}
