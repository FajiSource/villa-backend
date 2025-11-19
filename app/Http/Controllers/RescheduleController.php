<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\RescheduleRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RescheduleController extends Controller
{
    /**
     * Create a new reschedule request.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'booking_id' => 'required|integer|exists:bookings,id',
                'new_check_in' => 'required|date|after:now',
                'new_check_out' => 'required|date|after:new_check_in',
                'reason' => 'nullable|string|max:1000',
            ]);

            $booking = Booking::find($validated['booking_id']);
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'error' => 'Booking not found'
                ], 404);
            }

            // Users can only reschedule their own bookings
            if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized to reschedule this booking'
                ], 403);
            }

            // Check if booking is already cancelled
            if ($booking->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot reschedule a cancelled booking'
                ], 400);
            }

            // Check if there's already a pending reschedule request
            $existingRequest = RescheduleRequest::where('booking_id', $validated['booking_id'])
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'error' => 'You already have a pending reschedule request for this booking'
                ], 400);
            }

            $rescheduleRequest = RescheduleRequest::create([
                'booking_id' => $validated['booking_id'],
                'new_check_in' => $validated['new_check_in'],
                'new_check_out' => $validated['new_check_out'],
                'reason' => $validated['reason'] ?? null,
                'status' => 'pending',
            ]);

            $rescheduleRequest->load(['booking', 'responder']);

            // Create notification for reschedule request submission
            try {
                $booking = $rescheduleRequest->booking;
                $booking->load('villaAndCottage');
                $newCheckIn = \Carbon\Carbon::parse($rescheduleRequest->new_check_in)->format('M d, Y');
                $newCheckOut = \Carbon\Carbon::parse($rescheduleRequest->new_check_out)->format('M d, Y');
                Notification::create([
                    'user_id' => $booking->user_id,
                    'title' => 'Reschedule Request Submitted',
                    'message' => "Your reschedule request for booking #{$booking->id} has been submitted. New dates: {$newCheckIn} to {$newCheckOut}. Waiting for admin approval.",
                    'type' => 'booking',
                    'status' => 'unread',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create notification for reschedule request: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Reschedule request submitted successfully',
                'data' => $rescheduleRequest
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating reschedule request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to create reschedule request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reschedule requests for a booking.
     */
    public function getByBooking(Request $request, int $bookingId): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $booking = Booking::find($bookingId);
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'error' => 'Booking not found'
                ], 404);
            }

            // Users can only see reschedule requests for their own bookings, admins can see all
            if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }

            // Check if reschedule_requests table exists, if not return empty array
            try {
                $requests = RescheduleRequest::where('booking_id', $bookingId)
                    ->with(['booking', 'responder'])
                    ->orderBy('created_at', 'desc')
                    ->get();
            } catch (\Illuminate\Database\QueryException $e) {
                // Table doesn't exist yet - return empty array
                Log::warning('Reschedule requests table not found: ' . $e->getMessage());
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $requests
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching reschedule requests: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch reschedule requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all pending reschedule requests (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }

            $requests = RescheduleRequest::with(['booking.user', 'responder'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching reschedule requests: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch reschedule requests'
            ], 500);
        }
    }

    /**
     * Approve a reschedule request (admin only).
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }

            $rescheduleRequest = RescheduleRequest::with('booking')->find($id);
            
            if (!$rescheduleRequest) {
                return response()->json([
                    'success' => false,
                    'error' => 'Reschedule request not found'
                ], 404);
            }

            if ($rescheduleRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'error' => 'This reschedule request has already been processed'
                ], 400);
            }

            // Update the booking dates
            $booking = $rescheduleRequest->booking;
            $booking->check_in = $rescheduleRequest->new_check_in;
            $booking->check_out = $rescheduleRequest->new_check_out;
            $booking->save();

            // Update the reschedule request
            $rescheduleRequest->status = 'approved';
            $rescheduleRequest->responded_at = now();
            $rescheduleRequest->responded_by = $user->id;
            $rescheduleRequest->save();

            $rescheduleRequest->load(['booking', 'responder']);

            // Create notification for reschedule approval
            try {
                $booking = $rescheduleRequest->booking;
                $booking->load('villaAndCottage');
                $newCheckIn = \Carbon\Carbon::parse($rescheduleRequest->new_check_in)->format('M d, Y');
                $newCheckOut = \Carbon\Carbon::parse($rescheduleRequest->new_check_out)->format('M d, Y');
                Notification::create([
                    'user_id' => $booking->user_id,
                    'title' => 'Reschedule Request Approved',
                    'message' => "Great news! Your reschedule request for booking #{$booking->id} has been approved. New dates: {$newCheckIn} to {$newCheckOut}.",
                    'type' => 'booking',
                    'status' => 'unread',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create notification for reschedule approval: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Reschedule request approved',
                'data' => $rescheduleRequest
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error approving reschedule request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to approve reschedule request'
            ], 500);
        }
    }

    /**
     * Decline a reschedule request (admin only).
     */
    public function decline(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }

            $rescheduleRequest = RescheduleRequest::find($id);
            
            if (!$rescheduleRequest) {
                return response()->json([
                    'success' => false,
                    'error' => 'Reschedule request not found'
                ], 404);
            }

            if ($rescheduleRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'error' => 'This reschedule request has already been processed'
                ], 400);
            }

            // Update the reschedule request
            $rescheduleRequest->status = 'declined';
            $rescheduleRequest->responded_at = now();
            $rescheduleRequest->responded_by = $user->id;
            $rescheduleRequest->save();

            $rescheduleRequest->load(['booking', 'responder']);

            // Create notification for reschedule decline
            try {
                $booking = $rescheduleRequest->booking;
                $booking->load('villaAndCottage');
                Notification::create([
                    'user_id' => $booking->user_id,
                    'title' => 'Reschedule Request Declined',
                    'message' => "Unfortunately, your reschedule request for booking #{$booking->id} has been declined. Please contact us for more information or keep your original booking dates.",
                    'type' => 'booking',
                    'status' => 'unread',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create notification for reschedule decline: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Reschedule request declined',
                'data' => $rescheduleRequest
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error declining reschedule request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to decline reschedule request'
            ], 500);
        }
    }
}

