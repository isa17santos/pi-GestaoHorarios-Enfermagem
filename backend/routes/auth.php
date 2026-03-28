<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// ------------------ AUTH ------------------

// Public route used to authenticate a user and issue a token.
Route::post('/login', [AuthController::class, 'login']);


// Protected routes that require a valid Sanctum bearer token.
Route::middleware('auth:sanctum')->group(function () {

    // Return the currently authenticated user.
    Route::get('/me', [AuthController::class, 'me']);

    // Revoke the current access token.
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ------------------ AUTH ------------------
