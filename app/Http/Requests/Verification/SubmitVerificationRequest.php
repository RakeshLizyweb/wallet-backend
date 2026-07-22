<?php

namespace App\Http\Requests\Verification;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bufferMonths = config('wallet.verification.passport_expiry_buffer_months', 6);
        $minExpiry = now()->addMonths($bufferMonths)->toDateString();

        return [
            'passport_number' => ['required', 'string', 'max:20'],
            'passport_expiry' => ['required', 'date', 'after:'.$minExpiry],
            'passport_image' => ['required', 'image', 'max:5120'],
            'selfie_image' => ['required', 'image', 'max:5120'],
        ];
    }
}
