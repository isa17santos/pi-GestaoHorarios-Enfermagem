<?php

use App\Http\Controllers\Profile\ChangePasswordController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Profile endpoints for the authenticated user.
Route::middleware('auth:sanctum')->group(function () {
    // All profile routes require an authenticated token before controller logic runs.
    Route::get('profile', [ProfileController::class, 'show']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/change-password', ChangePasswordController::class);
    Route::get('profile/preferences', [ProfileController::class, 'showPreferences']);
    Route::patch('profile/preferences', [ProfileController::class, 'updatePreferences']);
    Route::delete('profile/preferences/{id}', [ProfileController::class, 'destroy']);
});
