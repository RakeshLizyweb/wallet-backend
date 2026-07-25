<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class AccountToAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'pin' => ['required', 'digits:'.config('wallet.pin.length')],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
