<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\NursePreferenceController;
use Illuminate\Support\Facades\Route;

// User listing and nurse preference lookup endpoints.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{id}', [UserController::class, 'update']); 
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/users/{id}/preferences', [NursePreferenceController::class, 'indexByUser']);
});


