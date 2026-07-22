<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pinLength = config('wallet.pin.length');

        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'otp' => ['required', 'digits:'.config('wallet.otp.length')],
            'pin' => ['required', 'digits:'.$pinLength, 'confirmed'],
        ];
    }
}
