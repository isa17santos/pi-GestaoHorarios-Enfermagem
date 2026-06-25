<?php

use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/vacations', [VacationController::class, 'index']);
    Route::post('/vacations', [VacationController::class, 'store']);
    Route::patch('/vacations/{id}', [VacationController::class, 'update']);
    Route::delete('/vacations/{id}', [VacationController::class, 'destroy']);
    Route::get('/vacations/{id}', [VacationController::class, 'show']);
});
