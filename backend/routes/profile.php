<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Profile endpoints for the authenticated user.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::get('profile/preferences', [ProfileController::class, 'showPreferences']);
    Route::patch('profile/preferences', [ProfileController::class, 'updatePreferences']);
});
