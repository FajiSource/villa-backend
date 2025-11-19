<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function bookingsPerYear(Request $request): JsonResponse
    {
        try {
            $driver = DB::getDriverName();
            $yearExpr = 'YEAR(created_at)';
            if ($driver === 'sqlite') {
                $yearExpr = 'strftime("%Y", created_at)';
            } elseif ($driver === 'pgsql') {
                $yearExpr = 'EXTRACT(YEAR FROM created_at)';
            }

            $bookings = Booking::selectRaw($yearExpr . ' as year, COUNT(*) as total')
                    ->groupBy('year')
                    ->orderBy('year', 'asc')
                    ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching bookings per year: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch stats'
            ], 500);
        }
    }

    public function bookingsToday(Request $request): JsonResponse
    {
        try {
            $today = date('Y-m-d');
            
            // Total bookings count
            $totalBookings = Booking::count();
            
            // Today's bookings: bookings with check-in today, check-out today, or created today
            $todayBookings = Booking::where(function($query) use ($today) {
                $query->whereDate('check_in', $today)
                      ->orWhereDate('check_out', $today)
                      ->orWhereDate('created_at', $today);
            })->count();
            
            // Get all accommodations
            $totalAccommodations = \App\Models\VillaAndCottage::count();
            
            // Get booked accommodations (accommodations with active bookings)
            $bookedAccommodations = \App\Models\VillaAndCottage::whereHas('bookings', function($query) use ($today) {
                $query->where('status', 'approved')
                      ->where(function($q) use ($today) {
                          $q->where('check_in', '<=', $today)
                            ->where('check_out', '>=', $today);
                      });
            })->count();
            
            // Available rooms = total - booked
            $availableRooms = $totalAccommodations - $bookedAccommodations;
            
            // Get today's bookings details for the list
            $todayBookingsList = Booking::with(['user', 'villaAndCottage'])
                ->where(function($query) use ($today) {
                    $query->whereDate('check_in', $today)
                          ->orWhereDate('check_out', $today)
                          ->orWhereDate('created_at', $today);
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($booking) {
                    return [
                        'id' => $booking->id,
                        'user_name' => $booking->user->name ?? 'N/A',
                        'accommodation_name' => $booking->villaAndCottage->name ?? 'N/A',
                        'check_in' => $booking->check_in,
                        'check_out' => $booking->check_out,
                        'status' => $booking->status,
                        'created_at' => $booking->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'bookings' => $totalBookings,
                    'today_count' => $todayBookings,
                    'rooms' => $availableRooms,
                    'total_rooms' => $totalAccommodations,
                    'booked_rooms' => $bookedAccommodations,
                    'today_bookings_list' => $todayBookingsList,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching today bookings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch stats: ' . $e->getMessage()
            ], 500);
        }
    }
}


