<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'otp' => ['required', 'digits:'.config('wallet.otp.length')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
