<?php

use App\Http\Controllers\NursePreferenceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftTypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ------------------ USER ------------------

// Returns the list of all existing users
Route::get('/users', [UserController::class, 'index']);

// Returns a certain user
Route::get('/users/{id}', [UserController::class, 'show']);

// ------------------ USER ------------------



// ---------------- SHIFT TYPE ---------------

// Returns a list of all existing shift types
Route::get('/shift-types', [ShiftTypeController::class, 'index']);

// Returns a certain shift type
Route::get('/shift-types/{id}', [ShiftTypeController::class, 'show']);

// ---------------- SHIFT TYPE ---------------



// ------------- NURSE PREFERENCES ------------

// Returns the list of all existing nurse preferences
Route::get('/nurse-preferences', [NursePreferenceController::class, 'index']);

// Returns the list of nurse preferences of a certain type of preference
Route::get('/nurse-preferences/by-shift-type/{type}', [NursePreferenceController::class, 'byShiftType']);

// ------------- NURSE PREFERENCES ------------



// ----------------- SCHEDULE -----------------

// Returns a list of all existing schedules
Route::get('/schedules', [ScheduleController::class, 'index']);

// Returns a certain schedule
Route::get('/schedules/{id}', [ScheduleController::class, 'show']);

// ----------------- SCHEDULE -----------------




// ------------------- SHIFT ------------------

// Returns a list of all existing shifts
Route::get('/shifts', [ShiftController::class, 'index']);

// Returns a certain shift
Route::get('/shifts/{id}', [ShiftController::class, 'show']);
// ------------------- SHIFT ------------------
