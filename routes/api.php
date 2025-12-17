<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\VillaAndCottageController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RescheduleController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AnnouncementController;
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

// Announcements api routes (public read, admin write)
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);
// Debug route - remove after testing
Route::get('/announcements-debug', function() {
    $total = \App\Models\Announcement::count();
    $all = \App\Models\Announcement::all();
    $active = \App\Models\Announcement::where('is_active', true)->count();
    
    return response()->json([
        'total' => $total,
        'active' => $active,
        'announcements' => $all->map(function($a) {
            return [
                'id' => $a->id,
                'title' => $a->title,
                'is_active' => $a->is_active,
                'published_at' => $a->published_at,
                'expires_at' => $a->expires_at,
            ];
        })
    ]);
});

// Stats and admin utilities
Route::get('/stats/bookings-per-year', [StatsController::class, 'bookingsPerYear']);
Route::get('/stats/bookings-report', [StatsController::class, 'bookingsReport']);
Route::get('/stats/bookings-today', [StatsController::class, 'bookingsToday']);
Route::get('/users/latest', [UserController::class, 'latest']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/admins', [UserController::class, 'admins']);
    Route::get('/users/customers', [UserController::class, 'customers']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
// Admin-only routes for villas/cottages
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/villas', [VillaAndCottageController::class, 'store']);
    Route::put('/villas/{id}', [VillaAndCottageController::class, 'update']);
    Route::post('/villas/{id}', [VillaAndCottageController::class, 'update']); // Support POST with _method=PUT
    Route::delete('/villas/{id}', [VillaAndCottageController::class, 'destroy']);

    // Booking admin actions
    Route::post('/bookings/{id}/approve', [BookingController::class, 'approve']);
    Route::post('/bookings/{id}/decline', [BookingController::class, 'decline']);

    // Reschedule admin actions
    Route::get('/reschedule-requests', [RescheduleController::class, 'index']);
    Route::post('/reschedule-requests/{id}/approve', [RescheduleController::class, 'approve']);
    Route::post('/reschedule-requests/{id}/decline', [RescheduleController::class, 'decline']);

    // Feedback statistics (admin only)
    Route::get('/feedback/statistics', [FeedbackController::class, 'getStatistics']);

    // Announcement admin actions
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);
    Route::post('/announcements/{id}', [AnnouncementController::class, 'update']); // Support POST with _method=PUT
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
});
// Booking api routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    Route::get('/bookings/availability/check', [BookingController::class, 'checkAvailability']);

    // Reschedule requests
    Route::post('/reschedule-requests', [RescheduleController::class, 'store']);
    Route::get('/reschedule-requests/booking/{bookingId}', [RescheduleController::class, 'getByBooking']);

    // Feedback routes
    Route::post('/feedback', [FeedbackController::class, 'store']);
    Route::get('/feedback/booking/{bookingId}', [FeedbackController::class, 'getByBooking']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    
    // Admin-only: Create system/promotion notifications
    Route::post('/notifications', [NotificationController::class, 'create']);
});