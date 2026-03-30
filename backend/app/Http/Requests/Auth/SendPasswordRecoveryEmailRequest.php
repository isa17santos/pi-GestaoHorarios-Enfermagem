<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendPasswordRecoveryEmailRequest extends FormRequest
{
    // Allows any user to make this request
    public function authorize(): bool
    {
        return true;
    }

    // Defines the validation rules for the password recovery email request.
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    // Defines custom validation error messages using translation keys.
    public function messages(): array
    {
        return [
            'email.required' => __('auth.email_required'),
            'email.email' => __('auth.email_invalid'),
        ];
    }
}
