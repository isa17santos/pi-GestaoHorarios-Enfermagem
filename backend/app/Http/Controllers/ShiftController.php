<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    // Returns the full list of shifts with their related schedule, type, and users.
    public function index(): JsonResponse
    {
        return response()->json(
            Shift::query()
                ->with(['schedule', 'shiftType', 'users'])
                ->get()
        );
    }

    // Returns a single shift by id with its related schedule, type, and users.
    public function show(int $id): JsonResponse
    {
        return response()->json(
            Shift::query()
                ->with(['schedule', 'shiftType', 'users'])
                ->findOrFail($id)
        );
    }
}
