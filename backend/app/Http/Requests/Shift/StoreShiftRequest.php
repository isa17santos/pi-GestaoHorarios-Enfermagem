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
            'user_ids.min' => 'O campo user_ids deve ter pelo menos um utilizador.',
            'user_ids.required' => 'O campo user_ids é obrigatório.',
            'user_ids.array' => 'O campo user_ids deve ser um array.',
        ];
    }
}
