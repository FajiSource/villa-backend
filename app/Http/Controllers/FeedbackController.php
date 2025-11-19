<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FeedbackController extends Controller
{
    /**
     * Store a newly created feedback in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Please login to submit feedback.'
                ], 401);
            }

            $validated = $request->validate([
                'booking_id' => 'required|integer|exists:bookings,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            // Check if booking exists and belongs to the user
            $booking = Booking::find($validated['booking_id']);
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'error' => 'Booking not found'
                ], 404);
            }

            // Check if booking belongs to the user
            if ($booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. This booking does not belong to you.'
                ], 403);
            }

            // Check if booking is completed
            $isCompleted = false;
            if ($booking->status === 'completed') {
                $isCompleted = true;
            } elseif ($booking->status === 'approved' && $booking->check_out) {
                // Check if check_out date has passed
                $checkOutDate = Carbon::parse($booking->check_out);
                if ($checkOutDate->isPast()) {
                    $isCompleted = true;
                    // Auto-update booking status to completed
                    $booking->status = 'completed';
                    $booking->save();
                }
            }

            if (!$isCompleted) {
                return response()->json([
                    'success' => false,
                    'error' => 'Feedback can only be submitted for completed bookings.'
                ], 400);
            }

            // Check if feedback already exists for this booking
            $existingFeedback = Feedback::where('booking_id', $validated['booking_id'])->first();
            
            if ($existingFeedback) {
                return response()->json([
                    'success' => false,
                    'error' => 'Feedback has already been submitted for this booking.'
                ], 400);
            }

            // Create feedback
            $validated['user_id'] = $user->id;
            $feedback = Feedback::create($validated);
            
            // Load relationships for response
            $feedback->load(['user', 'booking']);
            
            // Create notification to thank user for feedback
            try {
                $booking->load('villaAndCottage');
                $ratingText = $validated['rating'] >= 4 ? 'excellent' : ($validated['rating'] >= 3 ? 'good' : 'valuable');
                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Thank You for Your Feedback',
                    'message' => "Thank you for your {$ratingText} feedback! Your review for booking #{$booking->id} has been received. We appreciate your time and input.",
                    'type' => 'system',
                    'status' => 'unread',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create notification for feedback: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully',
                'data' => $feedback
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating feedback: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to submit feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get feedback for a specific booking.
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

            // Check if user has permission to view this booking
            if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized to view this booking'
                ], 403);
            }

            $feedback = Feedback::where('booking_id', $bookingId)
                ->with(['user'])
                ->first();

            return response()->json([
                'success' => true,
                'data' => $feedback
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching feedback: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch feedback'
            ], 500);
        }
    }

    /**
     * Get yearly rating statistics (admin only).
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $year = $request->query('year', date('Y'));

            // Get feedback statistics for the specified year
            $statistics = DB::table('feedbacks')
                ->join('bookings', 'feedbacks.booking_id', '=', 'bookings.id')
                ->whereYear('feedbacks.created_at', $year)
                ->select(
                    DB::raw('AVG(feedbacks.rating) as average_rating'),
                    DB::raw('COUNT(feedbacks.id) as total_feedback'),
                    DB::raw('COUNT(CASE WHEN feedbacks.rating = 5 THEN 1 END) as five_star_count'),
                    DB::raw('COUNT(CASE WHEN feedbacks.rating = 4 THEN 1 END) as four_star_count'),
                    DB::raw('COUNT(CASE WHEN feedbacks.rating = 3 THEN 1 END) as three_star_count'),
                    DB::raw('COUNT(CASE WHEN feedbacks.rating = 2 THEN 1 END) as two_star_count'),
                    DB::raw('COUNT(CASE WHEN feedbacks.rating = 1 THEN 1 END) as one_star_count')
                )
                ->first();

            // Get monthly breakdown
            $monthlyStats = DB::table('feedbacks')
                ->whereYear('created_at', $year)
                ->select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('AVG(rating) as average_rating'),
                    DB::raw('COUNT(id) as count')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'year' => (int)$year,
                    'average_rating' => $statistics->average_rating ? round((float)$statistics->average_rating, 2) : 0,
                    'total_feedback' => (int)$statistics->total_feedback,
                    'rating_distribution' => [
                        'five_star' => (int)$statistics->five_star_count,
                        'four_star' => (int)$statistics->four_star_count,
                        'three_star' => (int)$statistics->three_star_count,
                        'two_star' => (int)$statistics->two_star_count,
                        'one_star' => (int)$statistics->one_star_count,
                    ],
                    'monthly_breakdown' => $monthlyStats->map(function ($item) {
                        return [
                            'month' => (int)$item->month,
                            'average_rating' => round((float)$item->average_rating, 2),
                            'count' => (int)$item->count,
                        ];
                    })
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching feedback statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch statistics'
            ], 500);
        }
    }
}

