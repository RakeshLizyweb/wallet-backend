<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'nationality' => ['required', 'string', Rule::in(config('countries.list'))],
            'referral_code' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
