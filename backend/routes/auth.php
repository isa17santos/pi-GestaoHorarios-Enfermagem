<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// ------------------ AUTH ------------------

// Public route used to authenticate a user and issue a token.
Route::post('/login', [AuthController::class, 'login']);

// Sends a password recovery email with a reset link/token to the user.
Route::post('/password-recovery/email', [AuthController::class, 'sendPasswordRecoveryEmail']);

// Validates whether the provided password recovery token is valid and not expired.
Route::get('/password-recovery/validate-token', [AuthController::class, 'validatePasswordRecoveryToken']);

// Resets the user's password using a valid recovery token and the new password.
Route::post('/password-recovery/reset', [AuthController::class, 'resetPassword']);

// Protected routes that require a valid Sanctum bearer token.
Route::middleware('auth:sanctum')->group(function () {

    // Return the currently authenticated user.
    Route::get('/me', [AuthController::class, 'me']);

    // Revoke the current access token.
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ------------------ AUTH ------------------
