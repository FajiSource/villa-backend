<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $relationships = ['user', 'villaAndCottage'];
            
            // Add feedback relationship if table exists
            if (Schema::hasTable('feedbacks')) {
                $relationships[] = 'feedback';
            }
            
            $query = Booking::with($relationships);
            
            if ($user && $user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }
            
            $bookings = $query->orderBy('created_at', 'desc')->get();
            
            // Auto-update bookings to completed if check_out date has passed
            $updatedIds = [];
            foreach ($bookings as $booking) {
                if ($booking->status === 'approved' && $booking->check_out) {
                    $checkOutDate = \Carbon\Carbon::parse($booking->check_out);
                    if ($checkOutDate->isPast()) {
                        $oldStatus = $booking->status;
                        $booking->status = 'completed';
                        $booking->save();
                        $updatedIds[] = $booking->id;
                        
                        // Create notification when booking is completed
                        try {
                            $booking->load('villaAndCottage');
                            Notification::create([
                                'user_id' => $booking->user_id,
                                'title' => 'Stay Completed',
                                'message' => "Your stay at {$booking->villaAndCottage->name} (Booking #{$booking->id}) has been completed. We hope you enjoyed your stay! Please leave a review if you haven't already.",
                                'type' => 'booking',
                                'status' => 'unread',
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Failed to create notification for completed booking: ' . $e->getMessage());
                        }
                    }
                }
            }
            
            // Reload only updated bookings to refresh relationships
            if (!empty($updatedIds)) {
                $updatedBookings = Booking::with($relationships)
                    ->whereIn('id', $updatedIds)
                    ->get()
                    ->keyBy('id');
                
                // Replace updated bookings in the collection
                foreach ($bookings as $index => $booking) {
                    if (isset($updatedBookings[$booking->id])) {
                        $bookings[$index] = $updatedBookings[$booking->id];
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $bookings
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching bookings: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch bookings',
                'message' => $e->getMessage()
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
            
            // Create notification for booking submission
            try {
                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Booking Submitted',
                    'message' => "Your booking for {$booking->villaAndCottage->name} has been submitted and is pending approval. Booking ID: #{$booking->id}",
                    'type' => 'booking',
                    'status' => 'unread',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create notification for booking: ' . $e->getMessage());
            }
            
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
            
            $relationships = ['user', 'villaAndCottage'];
            
            // Add feedback relationship if table exists
            if (Schema::hasTable('feedbacks')) {
                $relationships[] = 'feedback';
            }
            
            $booking = Booking::with($relationships)->find($id);
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'error' => 'Booking not found'
                ], 404);
            }
            
            // Auto-update booking to completed if check_out date has passed
            if ($booking->status === 'approved' && $booking->check_out) {
                $checkOutDate = \Carbon\Carbon::parse($booking->check_out);
                if ($checkOutDate->isPast()) {
                    $booking->status = 'completed';
                    $booking->save();
                    // Reload to get updated status
                    $booking->load($relationships);
                    
                    // Create notification when booking is completed
                    try {
                        Notification::create([
                            'user_id' => $booking->user_id,
                            'title' => 'Stay Completed',
                            'message' => "Your stay at {$booking->villaAndCottage->name} (Booking #{$booking->id}) has been completed. We hope you enjoyed your stay! Please leave a review if you haven't already.",
                            'type' => 'booking',
                            'status' => 'unread',
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to create notification for completed booking: ' . $e->getMessage());
                    }
                }
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
        $booking = Booking::with('villaAndCottage')->find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'error' => 'Booking not found'], 404);
        }
        $booking->status = 'approved';
        $booking->approved_at = now();
        $booking->save();

        // Create notification for booking approval
        try {
            $checkInDate = \Carbon\Carbon::parse($booking->check_in)->format('M d, Y');
            $checkOutDate = \Carbon\Carbon::parse($booking->check_out)->format('M d, Y');
            Notification::create([
                'user_id' => $booking->user_id,
                'title' => 'Booking Approved',
                'message' => "Great news! Your booking #{$booking->id} for {$booking->villaAndCottage->name} has been approved. Check-in: {$checkInDate}, Check-out: {$checkOutDate}",
                'type' => 'booking',
                'status' => 'unread',
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create notification for booking approval: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Booking approved', 'data' => $booking], 200);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        $booking = Booking::with('villaAndCottage')->find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'error' => 'Booking not found'], 404);
        }
        $booking->status = 'declined';
        $booking->approved_at = null;
        $booking->save();

        // Create notification for booking decline
        try {
            Notification::create([
                'user_id' => $booking->user_id,
                'title' => 'Booking Declined',
                'message' => "Unfortunately, your booking #{$booking->id} for {$booking->villaAndCottage->name} has been declined. Please contact us for more information or try booking another date.",
                'type' => 'booking',
                'status' => 'unread',
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create notification for booking decline: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Booking declined', 'data' => $booking], 200);
    }

    /**
     * Cancel/Delete a booking.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $booking = Booking::find($id);
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'error' => 'Booking not found'
                ], 404);
            }

            // Users can only cancel their own bookings, admins can cancel any
            if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized to cancel this booking'
                ], 403);
            }

            // Update status to cancelled instead of deleting
            $booking->status = 'cancelled';
            $booking->save();

            // Create notification for booking cancellation
            try {
                $booking->load('villaAndCottage');
                $cancelledBy = $user->role === 'admin' ? 'the administrator' : 'you';
                Notification::create([
                    'user_id' => $booking->user_id,
                    'title' => 'Booking Cancelled',
                    'message' => "Your booking #{$booking->id} for {$booking->villaAndCottage->name} has been cancelled by {$cancelledBy}.",
                    'type' => 'booking',
                    'status' => 'unread',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create notification for booking cancellation: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => $booking
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error cancelling booking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel booking'
            ], 500);
        }
    }
}

