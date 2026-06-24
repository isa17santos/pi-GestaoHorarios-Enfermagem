<?php

use App\Http\Controllers\MedicalLeaveController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/medical-leaves', [MedicalLeaveController::class, 'index']);
    Route::post('/medical-leaves', [MedicalLeaveController::class, 'store']);
    Route::patch('/medical-leaves/{id}', [MedicalLeaveController::class, 'update']);
    Route::delete('/medical-leaves/{id}', [MedicalLeaveController::class, 'destroy']);
    Route::get('/medical-leaves/{id}', [MedicalLeaveController::class, 'show']);
});
