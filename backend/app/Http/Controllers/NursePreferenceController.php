<?php

namespace App\Http\Controllers;

use App\Models\NursePreference;
use Illuminate\Http\JsonResponse;

class NursePreferenceController extends Controller
{
    // Returns the full list of nurse preferences with related user and schedule data.
    public function index(): JsonResponse
    {
        return response()->json(
            NursePreference::query()
                ->with(['user', 'schedule'])
                ->get()
        );
    }

    // Returns nurse preferences filtered by shift type.
    public function byShiftType(string $type): JsonResponse
    {
        $column = match ($type) {
            'morning' => 'prefers_morning',
            'afternoon' => 'prefers_afternoon',
            'night' => 'prefers_night',
            default => null,
        };

        abort_unless($column !== null, 404, 'Shift type not supported for preferences.');

        return response()->json(
            NursePreference::query()
                ->with(['user', 'schedule'])
                ->where($column, true)
                ->get()
        );
    }
}
