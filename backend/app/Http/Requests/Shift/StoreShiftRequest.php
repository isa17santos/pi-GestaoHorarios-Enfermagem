<?php

namespace App\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    // Authorization is enforced in the controller based on user role.
    public function authorize(): bool
    {
        return true;
    }

    // Validate required foreign keys and shift date format.
    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'shift_type_id' => ['required', 'integer', 'exists:shift_types,id'],
            'shift_date' => ['required', 'date'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_id.required' => 'O campo schedule_id é obrigatório.',
            'schedule_id.integer' => 'O campo schedule_id deve ser um número inteiro.',
            'schedule_id.exists' => 'O schedule_id selecionado é inválido.',
            'shift_type_id.required' => 'O campo shift_type_id é obrigatório.',
            'shift_type_id.integer' => 'O campo shift_type_id deve ser um número inteiro.',
            'shift_type_id.exists' => 'O shift_type_id selecionado é inválido.',
            'shift_date.required' => 'O campo shift_date é obrigatório.',
            'shift_date.date' => 'O campo shift_date deve ser uma data válida.',
            'user_ids.min' => 'O campo user_ids deve ter pelo menos um utilizador.',
            'user_ids.required' => 'O campo user_ids é obrigatório.',
            'user_ids.array' => 'O campo user_ids deve ser um array.',
            'user_ids.*.integer' => 'Cada elemento de user_ids deve ser um número inteiro.',
            'user_ids.*.exists' => 'Um dos utilizadores selecionados em user_ids é inválido.',
        ];
    }
}
