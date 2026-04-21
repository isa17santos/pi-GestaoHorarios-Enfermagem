<?php

use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

// Schedule creation endpoints for planning periods.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::post('/schedules', [ScheduleController::class, 'store']);
    Route::patch('/schedules/{id}/publish', [ScheduleController::class, 'publish']);
});
