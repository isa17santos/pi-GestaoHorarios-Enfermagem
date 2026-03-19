<?php

namespace App\Http\Controllers;

use App\Models\ShiftType;
use Illuminate\Http\JsonResponse;

class ShiftTypeController extends Controller
{
    // Returns the full list of shift types.
    public function index(): JsonResponse
    {
        return response()->json(
            ShiftType::query()->get()
        );
    }

    // Returns a single shift type by id.
    public function show(int $id): JsonResponse
    {
        return response()->json(
            ShiftType::query()->findOrFail($id)
        );
    }
}
