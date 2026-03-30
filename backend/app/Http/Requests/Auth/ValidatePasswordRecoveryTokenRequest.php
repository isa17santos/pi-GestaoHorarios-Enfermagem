<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidatePasswordRecoveryTokenRequest extends FormRequest
{
    // Allows any user to make this request
    public function authorize(): bool
    {
        return true;
    }

    // Defines the validation rules for checking a password recovery token.
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ];
    }

    // Defines custom validation error messages using translation keys.
    public function messages(): array
    {
        return [
            'token.required' => __('auth.reset_token_required'),
            'email.required' => __('auth.email_required'),
            'email.email' => __('auth.email_invalid'),
        ];
    }
}
