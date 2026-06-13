<?php

use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftSwapRequestController;
use App\Http\Controllers\SwapRequestController;
use App\Http\Controllers\SwapValidationController;
use Illuminate\Support\Facades\Route;

// Compose API routes by domain to keep the main file minimal.
require __DIR__.'/auth.php';
require __DIR__.'/profile.php';
require __DIR__.'/shift-types.php';
require __DIR__.'/users.php';
require __DIR__.'/schedules.php';
require __DIR__.'/shifts.php';
require __DIR__.'/notifications.php';
