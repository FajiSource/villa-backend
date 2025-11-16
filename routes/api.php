<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\VillaAndCottageController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    // alias for current user
    Route::get('/me', function (Request $request) {
        return response()->json($request->user());
    });
});

// Authentication api routes
Route::post('/register',[AuthController::class,'signUpUser']);
Route::post('/login',[AuthController::class,'signInUser']);
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');

// Villas and Cottages api routes (public read, admin write)
Route::get('/villas', [VillaAndCottageController::class, 'index']);
Route::get('/villas/{id}', [VillaAndCottageController::class, 'show']);

// Stats and admin utilities
Route::get('/stats/bookings-per-year', [StatsController::class, 'bookingsPerYear']);
Route::get('/stats/bookings-today', [StatsController::class, 'bookingsToday']);
Route::get('/users/latest', [UserController::class, 'latest']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/admins', [UserController::class, 'admins']);
    Route::get('/users/customers', [UserController::class, 'customers']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
// Admin-only routes for villas/cottages
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/villas', [VillaAndCottageController::class, 'store']);
    Route::put('/villas/{id}', [VillaAndCottageController::class, 'update']);
    Route::delete('/villas/{id}', [VillaAndCottageController::class, 'destroy']);

    // Booking admin actions
    Route::post('/bookings/{id}/approve', [BookingController::class, 'approve']);
    Route::post('/bookings/{id}/decline', [BookingController::class, 'decline']);
});
// Booking api routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
});