<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    // Determine if the user is authorized to make this request.
    public function authorize(): bool
    {
        // Authorization is handled by the auth:sanctum middleware.
        return true;
    }

    // Get the validation rules that apply to the request.
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name'                  => ['sometimes', 'string', 'max:255'],
            'email'                 => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password'              => ['sometimes', 'string', 'min:8', 'confirmed'],
        ];
    }

    // Define custom validation error messages using translation keys.
    public function messages(): array
    {
        return [
            'email.unique' => __('auth.email_already_in_use'),
            'email.email' => __('auth.email_invalid'),
            'password.min' => __('auth.password_min'),
            'password.confirmed' => __('auth.password_confirmation_mismatch'),
        ];
    }
}
