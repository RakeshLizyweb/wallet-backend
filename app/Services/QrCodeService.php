<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeService
{
    public function __construct(protected TransferService $transferService)
    {
    }

    public function payloadFor(User $user): array
    {
        return [
            'v' => 1,
            'type' => 'wallet_pay',
            'upi' => $user->upi_handle,
        ];
    }

    public function generateDataUri(User $user): string
    {
        return $this->render($user)->getDataUri();
    }

    public function generatePng(User $user): string
    {
        return $this->render($user)->getString();
    }

    protected function render(User $user)
    {
        $qrCode = new QrCode(
            data: json_encode($this->payloadFor($user)),
            size: 320,
            margin: 10,
        );

        return (new PngWriter())->write($qrCode);
    }

    public function validate(string $payload): array
    {
        $upiHandle = $this->extractUpiHandle($payload);

        $user = $this->transferService->resolveReceiver($upiHandle);

        return [
            'name' => $user->name,
            'upi_handle' => $user->upi_handle,
            'wallet_number' => $user->wallet?->wallet_number,
        ];
    }

    protected function extractUpiHandle(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (is_array($decoded) && ! empty($decoded['upi'])) {
            return $decoded['upi'];
        }

        if (str_contains($payload, '@')) {
            return $payload;
        }

        throw new ApiException('Invalid QR code payload.', 422);
    }
}
