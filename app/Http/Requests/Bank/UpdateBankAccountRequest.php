<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => ['sometimes', 'string', 'max:150'],
            'account_holder_name' => ['sometimes', 'string', 'max:150'],
            'account_type' => ['sometimes', Rule::in(['savings', 'current'])],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
