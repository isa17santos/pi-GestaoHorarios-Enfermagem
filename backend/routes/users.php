<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\NursePreferenceController;
use Illuminate\Support\Facades\Route;

// User listing and nurse preference lookup endpoints.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}/preferences', [NursePreferenceController::class, 'indexByUser']);
});


