<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendPasswordRecoveryEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('auth.email_required'),
            'email.email' => __('auth.email_invalid'),
        ];
    }
}
