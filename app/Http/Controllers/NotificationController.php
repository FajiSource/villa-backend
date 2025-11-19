<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notification not found'
                ], 404);
            }

            $notification->status = 'read';
            $notification->save();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $updated = Notification::where('user_id', $user->id)
                ->where('status', 'unread')
                ->update(['status' => 'read']);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'count' => $updated
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark all notifications as read'
            ], 500);
        }
    }

    /**
     * Get unread notification count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $count = Notification::where('user_id', $user->id)
                ->where('status', 'unread')
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching unread count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch unread count'
            ], 500);
        }
    }

    /**
     * Delete a notification.
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

            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete notification'
            ], 500);
        }
    }

    /**
     * Create a system or promotion notification (admin only).
     * Can send to a specific user or all customers.
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only admins can create notifications.'
                ], 403);
            }

            $validated = $request->validate([
                'user_id' => 'nullable|integer|exists:users,id',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|in:booking,system,promotion',
            ]);

            if ($validated['user_id']) {
                // Send to specific user
                Notification::create([
                    'user_id' => $validated['user_id'],
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'type' => $validated['type'],
                    'status' => 'unread',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Notification created successfully',
                    'count' => 1
                ], 201);
            } else {
                // Send to all customers
                $customers = User::where('role', 'customer')->get();
                $count = 0;
                
                foreach ($customers as $customer) {
                    Notification::create([
                        'user_id' => $customer->id,
                        'title' => $validated['title'],
                        'message' => $validated['message'],
                        'type' => $validated['type'],
                        'status' => 'unread',
                    ]);
                    $count++;
                }

                return response()->json([
                    'success' => true,
                    'message' => "Notification sent to {$count} customers",
                    'count' => $count
                ], 201);
            }
        } catch (\Exception $e) {
            Log::error('Error creating notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to create notification: ' . $e->getMessage()
            ], 500);
        }
    }
}

