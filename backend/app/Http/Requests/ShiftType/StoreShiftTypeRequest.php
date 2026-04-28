<?php

namespace App\Http\Requests\ShiftType;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftTypeRequest extends FormRequest
{
    // Authorization is enforced in the controller based on user role.
    public function authorize(): bool
    {
        return true;
    }

    // Validate required shift type fields.
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'start_time' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'end_time' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'min_nurses' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O campo name e obrigatorio.',
            'name.string' => 'O campo name deve ser uma string.',
            'name.max' => 'O campo name nao pode ter mais de 255 caracteres.',
            'color.required' => 'O campo color e obrigatorio.',
            'color.regex' => 'O campo color deve estar no formato hexadecimal, por exemplo #1A73E8.',
            'start_time.required' => 'O campo start_time e obrigatorio.',
            'start_time.regex' => 'O campo start_time deve estar no formato HH:MM ou HH:MM:SS.',
            'end_time.required' => 'O campo end_time e obrigatorio.',
            'end_time.regex' => 'O campo end_time deve estar no formato HH:MM ou HH:MM:SS.',
            'min_nurses.required' => 'O campo min_nurses e obrigatorio.',
            'min_nurses.integer' => 'O campo min_nurses deve ser um numero inteiro.',
            'min_nurses.min' => 'O campo min_nurses deve ser no minimo 0.',
        ];
    }
}
