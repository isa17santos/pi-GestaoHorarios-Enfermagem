<?php

use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

// Individual shift creation endpoint.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/shifts', [ShiftController::class, 'store']);
    Route::patch('/shifts/{id}', [ShiftController::class, 'update']);
    Route::delete('/shifts/{id}', [ShiftController::class, 'destroy']);
});
