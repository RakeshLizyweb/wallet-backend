<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:150'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'digits_between:9,18'],
            'ifsc_code' => ['required', 'regex:/^[A-Za-z]{4}0[A-Z0-9]{6}$/'],
            'account_type' => ['sometimes', Rule::in(['savings', 'current'])],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
