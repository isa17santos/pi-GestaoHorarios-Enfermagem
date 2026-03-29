<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidatePasswordRecoveryTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => __('auth.reset_token_required'),
            'email.required' => __('auth.email_required'),
            'email.email' => __('auth.email_invalid'),
        ];
    }
}
