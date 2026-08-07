<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class LookupContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phones' => ['required', 'array', 'min:1', 'max:1000'],
            'phones.*' => ['required', 'string', 'min:3', 'max:20'],
        ];
    }
}
