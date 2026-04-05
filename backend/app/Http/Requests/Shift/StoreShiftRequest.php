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
        ];
    }
}
