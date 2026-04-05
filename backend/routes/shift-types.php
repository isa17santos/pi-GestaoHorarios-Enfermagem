<?php

use App\Http\Controllers\ShiftTypeController;
use Illuminate\Support\Facades\Route;

// Shift type endpoints used when building schedules.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/shift-types', [ShiftTypeController::class, 'index']);
});
