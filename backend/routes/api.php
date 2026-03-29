<?php

use App\Http\Controllers\Api\DriverLocationController;
use App\Http\Controllers\Api\GeofenceController;
use Illuminate\Support\Facades\Route;

Route::get('driver-locations/devices', [DriverLocationController::class, 'devices']);
Route::get('driver-locations/history', [DriverLocationController::class, 'history']);

Route::apiResource('geofences', GeofenceController::class);
