<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $length = config('wallet.pin.length');

        return [
            'current_pin' => ['required', 'digits:'.$length],
            'pin' => ['required', 'digits:'.$length, 'confirmed', 'different:current_pin'],
        ];
    }
}
