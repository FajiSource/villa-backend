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
            $count = Booking::whereDate('created_at', $today)->count();

            return response()->json([
                'success' => true,
                'data' => ['count' => $count]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching today bookings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch stats'
            ], 500);
        }
    }
}


