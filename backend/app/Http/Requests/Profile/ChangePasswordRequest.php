<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function stopOnFirstFailure(): bool
    {
        return true;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Hash::check($value, $this->user()->password)) {
                        $fail(__('auth.current_password_incorrect'));
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->symbols(),
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => __('auth.password_required'),
            'password.required' => __('auth.password_required'),
            'password.confirmed' => __('auth.password_confirmation_mismatch'),
            'password.min' => __('auth.password_complexity'),
            'password.mixed' => __('auth.password_complexity'),
            'password.symbols' => __('auth.password_complexity'),
            'password_confirmation.required' => __('auth.password_required'),
        ];
    }
}