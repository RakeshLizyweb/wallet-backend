<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['credit', 'debit'])],
            'category' => ['sometimes', Rule::in([
                'wallet_to_wallet', 'wallet_to_bank', 'bank_to_wallet',
                'reward', 'refund', 'fee', 'adjustment', 'reversal',
            ])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
