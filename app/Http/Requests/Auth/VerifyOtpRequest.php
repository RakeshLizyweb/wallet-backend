<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'otp' => ['required', 'digits:'.config('wallet.otp.length')],
            'purpose' => ['required', Rule::in(['registration', 'login'])],
            'device' => ['sometimes', 'array'],
            'device.device_id' => ['required_with:device', 'string', 'max:191'],
            'device.device_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'device.platform' => ['sometimes', 'nullable', Rule::in(['ios', 'android', 'web'])],
            'device.fcm_token' => ['sometimes', 'nullable', 'string'],
            'device.app_version' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
