<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    // Returns the full list of schedules with their main relationships.
    public function index(): JsonResponse
    {
        return response()->json(
            Schedule::query()
                ->with(['creator', 'users', 'shifts.shiftType'])
                ->get()
        );
    }

    // Returns a single schedule by id with its main relationships.
    public function show(int $id): JsonResponse
    {
        return response()->json(
            Schedule::query()
                ->with(['creator', 'users', 'shifts.shiftType'])
                ->findOrFail($id)
        );
    }
}
