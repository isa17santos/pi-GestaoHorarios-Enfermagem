<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    // Determine if the user is authorized to make this request.
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $roleValue = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        // Only the admin is authorized to use this profile update endpoint
        return $roleValue === 'admin';
    }

    // Get the validation rules that apply to the request.
    public function rules(): array
    {
        // Ignore the authenticated user when checking unique email to allow keeping current email.
        $userId = $this->user()->id;

        return [
            'name'                  => ['sometimes', 'string', 'max:255'],
            'email'                 => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
        ];
    }

    // Define custom validation error messages using translation keys.
    public function messages(): array
    {
        // Keep API validation messages aligned with existing translation keys.
        return [
            'email.unique' => __('auth.email_already_in_use'),
            'email.email' => __('auth.email_invalid'),
        ];
    }
}
