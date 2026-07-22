<?php

namespace App\Http\Requests\Qr;

use Illuminate\Foundation\Http\FormRequest;

class ValidateQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'string'],
        ];
    }
}
