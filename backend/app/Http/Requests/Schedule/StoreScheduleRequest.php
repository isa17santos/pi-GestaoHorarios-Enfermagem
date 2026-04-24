<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    // Authorization is enforced in the controller based on user role.
    public function authorize(): bool
    {
        return true;
    }

    // Validate schedule date window consistency.
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'O campo start_date é obrigatório.',
            'start_date.date' => 'O campo start_date deve ser uma data válida.',
            'end_date.required' => 'O campo end_date é obrigatório.',
            'end_date.date' => 'O campo end_date deve ser uma data válida.',
            'end_date.after_or_equal' => 'O campo end_date deve ser uma data posterior ou igual a start_date.',
        ];
    }
}
