<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;


class StoreUserRequest extends FormRequest
{
    // Determine if the user is authorized to make this request.
    public function authorize(): bool
    {
        // Route protection: Only users with the 'Admin' role can proceed
        return $this->user()->role === UserRole::Admin;
    }

    // Get the validation rules that apply to the request.
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::enum(UserRole::class)], // Validates if the submitted role exists in the UserRole Enum
            'active' => ['required', 'boolean'],
        ];
    }

    // Handle a failed authorization attempt (Error 403)
    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Apenas administradores podem realizar esta ação.'
        ], 403));
    }

    // Handle a failed validation attempt (Error 422)
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Dados invalidos ou em falta.'
        ], 422));
    }
}
