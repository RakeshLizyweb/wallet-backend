<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WalletAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'bucket' => ['sometimes', Rule::in(['account', 'wallet'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'force' => ['sometimes', 'boolean'],
            'otp' => ['sometimes', 'digits:'.config('wallet.otp.length')],
            'admin_password' => ['sometimes', 'string'],
        ];
    }

    /**
     * Debiting a user's balance is destructive, so it requires either the
     * user's own consent (an OTP sent to their phone) or the admin
     * deliberately overriding that with their own password ("force debit").
     * Credits need neither.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== 'debit') {
                return;
            }

            if ($this->boolean('force')) {
                if (! $this->filled('admin_password')) {
                    $validator->errors()->add('admin_password', 'Your admin password is required to force a debit.');
                }
            } elseif (! $this->filled('otp')) {
                $validator->errors()->add('otp', 'An OTP sent to the user is required to debit funds (or use force debit with your admin password).');
            }
        });
    }
}
