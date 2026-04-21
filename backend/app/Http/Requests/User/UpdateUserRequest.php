<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use \App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;


class UpdateUserRequest extends FormRequest
{
    // Determine if the user is authorized to make this request.
    public function authorize(): bool
    {
        // Finds the user
        $userExists = User::find($this->route('id'));

        // if it is null, throws the 404 error.
        if (!$userExists) {
            throw new HttpResponseException(response()->json([
                'message' => 'Utilizador não encontrado.'
            ], 404));
        }

        // Route protection: Only users with the 'Admin' role can proceed
        return $this->user()->role === UserRole::Admin;
    }

    // Get the validation rules that apply to the request.
    public function rules(): array
    {
        // The user ID to edit comes from the route parameter 
        $userId = $this->route('id');

        return [
            // 'sometimes' allows fields to be optional in the request, 
            // but if they are sent, they must respect the rules defined below.
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            
            // Validates the email ensuring it's unique, but ignoring the email of the user being edited
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            
            'role' => ['sometimes', 'required', 'string', Rule::enum(UserRole::class)],
            'active' => ['sometimes', 'required', 'boolean'],
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
