<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use \App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;


class DestroyUserRequest extends FormRequest
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
        // No validation rules needed for deleting, just authorization.
        return [];
    }

    // Handle a failed authorization attempt (Error 403)
    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Apenas administradores podem realizar esta ação.'
        ], 403));
    }
}
