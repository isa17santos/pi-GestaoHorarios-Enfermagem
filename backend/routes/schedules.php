<?php

use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;


Route::get('/schedules/ical', [ScheduleController::class, 'ical']);


// Schedule creation endpoints for planning periods.
Route::middleware('auth:sanctum')->group(function () {

    // View Current Schedule
    Route::get('/schedules/weekly', [ScheduleController::class, 'weekly']);


    // Create Schedule
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{id}', [ScheduleController::class, 'show']);
    Route::post('/schedules', [ScheduleController::class, 'store']);
    Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy']);
    Route::patch('/schedules/{id}/publish', [ScheduleController::class, 'publish']);
    Route::get('/schedules/{id}/shifts', [ScheduleController::class, 'shifts']);

    // Edit Schedule
    Route::post('/schedules/{id}/edit', [ScheduleController::class, 'startEdit']);
    Route::post('/schedules/{id}/publish-edit', [ScheduleController::class, 'publishEdit']);

});
