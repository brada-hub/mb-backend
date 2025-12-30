<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user' => 'required|string',
            'password' => 'required|string',
            'uuid_celular' => 'nullable|string',
            'platform' => 'required|in:web,mobile' // To distinguish Director vs Musician entry point
        ];
    }
}
