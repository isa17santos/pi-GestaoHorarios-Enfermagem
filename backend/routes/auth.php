<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// ------------------ AUTH ------------------

// Public route used to authenticate a user and issue a token.
Route::post('/login', [AuthController::class, 'login']);

Route::post('/password-recovery/email', [AuthController::class, 'sendPasswordRecoveryEmail']);
Route::get('/password-recovery/validate-token', [AuthController::class, 'validatePasswordRecoveryToken']);
Route::post('/password-recovery/reset', [AuthController::class, 'resetPassword']);

// Protected routes that require a valid Sanctum bearer token.
Route::middleware('auth:sanctum')->group(function () {

    // Return the currently authenticated user.
    Route::get('/me', [AuthController::class, 'me']);

    // Revoke the current access token.
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ------------------ AUTH ------------------
