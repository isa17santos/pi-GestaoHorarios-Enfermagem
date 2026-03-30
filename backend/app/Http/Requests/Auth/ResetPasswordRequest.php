<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    // Allows any user to make this request
    public function authorize(): bool
    {
        return true;
    }

    // Defines the validation rules for the password reset request payload.
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->symbols(),
            ],
        ];
    }

    // Defines custom validation error messages using translation keys.
    public function messages(): array
    {
        return [
            'token.required' => __('auth.reset_token_required'),
            'email.required' => __('auth.email_required'),
            'email.email' => __('auth.email_invalid'),
            'password.required' => __('auth.password_required'),
            'password.confirmed' => __('auth.password_confirmation_mismatch'),
            'password.min' => __('auth.password_complexity'),
            'password.mixed' => __('auth.password_complexity'),
            'password.symbols' => __('auth.password_complexity'),
        ];
    }
}
