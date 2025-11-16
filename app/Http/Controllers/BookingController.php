<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();
            
            // If user is admin, show all bookings; otherwise show only user's bookings
            $query = Booking::with(['user', 'villaAndCottage']);
            
            if ($user && $user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }
            
            $bookings = $query->orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $bookings
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching bookings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch bookings'
            ], 500);
        }
    }

    /**
     * Store a newly created booking in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Please login to make a booking.'
                ], 401);
            }

            $validated = $request->validate([
                'rc_id' => 'required|integer|exists:villas_and_cottages,id',
                'name' => 'required|string|max:255',
                'contact' => 'required|string|max:255',
                'check_in' => 'required|date|after:now',
                'check_out' => 'required|date|after:check_in',
                'pax' => 'required|integer|min:1',
                'special_req' => 'nullable|string|max:1000',
            ]);
            
            // Add user_id from authenticated user
            $validated['user_id'] = $user->id;
            $validated['status'] = 'pending';
            
            // Convert check_in and check_out to datetime format
            $validated['check_in'] = date('Y-m-d H:i:s', strtotime($validated['check_in']));
            $validated['check_out'] = date('Y-m-d H:i:s', strtotime($validated['check_out']));
            
            // Handle nullable special_req - convert null to empty string if needed
            if (!isset($validated['special_req']) || $validated['special_req'] === null) {
                $validated['special_req'] = '';
            }
            
            $booking = Booking::create($validated);
            
            // Load relationships for response
            $booking->load(['user', 'villaAndCottage']);
            
            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => $booking
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating booking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to create booking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified booking.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $request->merge(['id' => $id]);
            $request->validate([
                'id' => 'required|integer|min:1',
            ]);

            $user = $request->user();
            
            $booking = Booking::with(['user', 'villaAndCottage'])->find($id);
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'error' => 'Booking not found'
                ], 404);
            }
            
            // Check if user has permission to view this booking
            if ($user && $user->role !== 'admin' && $booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized to view this booking'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'data' => $booking
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching booking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch booking'
            ], 500);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'error' => 'Booking not found'], 404);
        }
        $booking->status = 'approved';
        $booking->approved_at = now();
        $booking->save();

        return response()->json(['success' => true, 'message' => 'Booking approved', 'data' => $booking], 200);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'error' => 'Booking not found'], 404);
        }
        $booking->status = 'declined';
        $booking->approved_at = null;
        $booking->save();

        return response()->json(['success' => true, 'message' => 'Booking declined', 'data' => $booking], 200);
    }
}

