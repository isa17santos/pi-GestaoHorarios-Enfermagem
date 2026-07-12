<?php

use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftSwapRequestController;
use App\Http\Controllers\SwapRequestController;
use App\Http\Controllers\SwapValidationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
	Route::get('/shifts', [ShiftController::class, 'index']);
	Route::get('/swaps/validate', [SwapValidationController::class, 'validate']);
	Route::get('/swaps', [SwapRequestController::class, 'index']);
	Route::get('/swaps/{swapRequest}', [SwapRequestController::class, 'show']);
	Route::post('/swaps', [SwapRequestController::class, 'store']);
	Route::post('/swaps/{swapRequest}/accept', [SwapRequestController::class, 'accept']);
	Route::post('/swaps/{swapRequest}/reject', [ShiftSwapRequestController::class, 'reject']);
	Route::post('/swaps/{swapRequest}/cancel', [SwapRequestController::class, 'cancel']);
});
