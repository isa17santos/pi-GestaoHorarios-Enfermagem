<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StoreSwapRequest extends FormRequest
{
    // Authorization is handled by auth:sanctum middleware.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'offered_shift_ids' => ['required', 'array', 'min:1'],
            'offered_shift_ids.*' => ['integer', 'exists:shifts,id'],
            'requested_shift_ids' => ['required', 'array', 'min:1'],
            'requested_shift_ids.*' => ['integer', 'exists:shifts,id'],
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    // Ensure offered shifts belong to the authenticated user and are not too soon.
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $offeredShiftIds = array_map('intval', (array) $this->input('offered_shift_ids', []));
            if ($offeredShiftIds === []) {
                return;
            }

            $user = $this->user();

            $ownedShiftIds = DB::table('user_shifts')
                ->whereIn('shift_id', $offeredShiftIds)
                ->where('user_id', $user->id)
                ->pluck('shift_id')
                ->all();

            $ownedShiftIds = array_map('intval', $ownedShiftIds);
            $notOwnedShiftIds = array_diff($offeredShiftIds, $ownedShiftIds);

            if ($notOwnedShiftIds !== []) {
                $validator->errors()->add('offered_shift_ids', __('swap.offered_shift_ids.exists'));
                return;
            }

            $minimumAllowedDate = Carbon::today()->addDays(2);
            $tooSoonExists = DB::table('shifts')
                ->whereIn('id', $offeredShiftIds)
                ->whereDate('shift_date', '<=', $minimumAllowedDate)
                ->exists();

            if ($tooSoonExists) {
                $validator->errors()->add('offered_shift_ids', __('swap.offered_shift_too_soon'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'offered_shift_ids.required' => __('swap.offered_shift_ids.required'),
            'offered_shift_ids.array' => __('swap.offered_shift_ids.array'),
            'offered_shift_ids.min' => __('swap.offered_shift_ids.min'),
            'offered_shift_ids.*.integer' => __('swap.offered_shift_ids.integer'),
            'offered_shift_ids.*.exists' => __('swap.offered_shift_ids.exists'),

            'requested_shift_ids.required' => __('swap.requested_shift_ids.required'),
            'requested_shift_ids.array' => __('swap.requested_shift_ids.array'),
            'requested_shift_ids.min' => __('swap.requested_shift_ids.min'),
            'requested_shift_ids.*.integer' => __('swap.requested_shift_ids.integer'),
            'requested_shift_ids.*.exists' => __('swap.requested_shift_ids.exists'),

            'target_user_id.required' => __('swap.target_user_id.required'),
            'target_user_id.integer' => __('swap.target_user_id.integer'),
            'target_user_id.exists' => __('swap.target_user_id.exists'),

            'notes.string' => __('swap.notes.string'),
            'notes.max' => __('swap.notes.max'),
        ];
    }
}
