<?php

use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftSwapRequestController;
use App\Http\Controllers\SwapRequestController;
use Illuminate\Support\Facades\Route;

// Compose API routes by domain to keep the main file minimal.
require __DIR__.'/auth.php';
require __DIR__.'/profile.php';
require __DIR__.'/shift-types.php';
require __DIR__.'/users.php';
require __DIR__.'/schedules.php';
require __DIR__.'/shifts.php';

Route::middleware('auth:sanctum')->group(function (): void {
	Route::get('/shifts', [ShiftController::class, 'index']);
	Route::get('/swaps', [SwapRequestController::class, 'index']);
	Route::get('/swaps/{swapRequest}', [SwapRequestController::class, 'show']);
	Route::post('/swaps', [SwapRequestController::class, 'store']);
	Route::post('/swaps/{swapRequest}/accept', [SwapRequestController::class, 'accept']);
	Route::post('/swaps/{swapRequest}/reject', [ShiftSwapRequestController::class, 'reject']);
	Route::post('/swaps/{swapRequest}/cancel', [SwapRequestController::class, 'cancel']);
});
