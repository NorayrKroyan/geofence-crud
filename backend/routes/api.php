<?php

use App\Http\Controllers\Api\GeofenceController;
use Illuminate\Support\Facades\Route;

Route::apiResource('geofences', GeofenceController::class);
