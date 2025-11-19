<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of announcements (public).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check if user is authenticated and is admin
            // Try multiple methods to get the authenticated user
            $user = $request->user() ?? auth()->guard('sanctum')->user() ?? auth()->user() ?? Auth::user() ?? null;
            $isAdmin = $user && $user->role === 'admin';

            // First, check total count in database BEFORE any queries
            $totalCount = Announcement::count();
            
            Log::info('Announcements API called', [
                'user_id' => $user?->id,
                'user_role' => $user?->role ?? 'none',
                'is_admin' => $isAdmin,
                'has_user' => $user !== null,
                'total_in_db' => $totalCount,
                'auth_header' => $request->header('Authorization') ? 'present' : 'missing'
            ]);

            // Admin sees all announcements (including inactive), clients see only active ones
            if ($isAdmin) {
                // Admin: Show all announcements regardless of status
                // MySQL compatible ordering (NULLS LAST not supported, so use COALESCE)
                $announcements = Announcement::orderByRaw('COALESCE(priority, 0) DESC')
                    ->orderByRaw('COALESCE(published_at, created_at) DESC')
                    ->orderBy('created_at', 'desc')
                    ->get();
                Log::info('Admin query: fetched all announcements', [
                    'count' => $announcements->count(),
                    'total_in_db' => $totalCount,
                    'user_id' => $user?->id,
                    'user_role' => $user?->role
                ]);
            } else {
                // Client/Public: Show only active announcements that are published and not expired
                $now = now();
                
                // Get active announcements and filter by publish/expire dates
                $announcements = Announcement::where('is_active', true)
                    ->where(function($q) use ($now) {
                        // Published: null means publish immediately, or published_at <= now
                        $q->whereNull('published_at')
                          ->orWhere('published_at', '<=', $now);
                    })
                    ->where(function($q) use ($now) {
                        // Expires: null means never expires, or expires_at >= now
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>=', $now);
                    })
                    ->orderByRaw('COALESCE(priority, 0) DESC')
                    ->orderByRaw('COALESCE(published_at, created_at) DESC')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
                Log::info('Client query: fetched active announcements', [
                    'count' => $announcements->count(),
                    'total_in_db' => $totalCount,
                    'active_count' => Announcement::where('is_active', true)->count(),
                    'now' => $now->format('Y-m-d H:i:s')
                ]);
            }
            
            Log::info('Final announcements count', [
                'count' => $announcements->count(),
                'is_admin' => $isAdmin
            ]);

            $announcements = $announcements->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'content' => $announcement->content,
                    'image' => $announcement->image ? Storage::url($announcement->image) : null,
                    'published_at' => $announcement->published_at?->toDateString(),
                    'expires_at' => $announcement->expires_at?->toDateString(),
                    'is_active' => $announcement->is_active,
                    'priority' => $announcement->priority,
                    'created_at' => $announcement->created_at,
                ];
            });

            Log::info('Announcements fetched', [
                'count' => $announcements->count(),
                'is_admin' => $isAdmin,
                'announcements' => $announcements
            ]);

            return response()->json([
                'success' => true,
                'data' => $announcements
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching announcements: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch announcements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created announcement (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Check if user is admin
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only admins can create announcements.'
                ], 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
                'published_at' => 'nullable|date',
                'expires_at' => 'nullable|date|after:published_at',
                'is_active' => 'boolean',
                'priority' => 'integer|min:0|max:100',
            ]);

            // Convert empty strings to null for date fields
            if (isset($validated['published_at']) && $validated['published_at'] === '') {
                $validated['published_at'] = null;
            }
            if (isset($validated['expires_at']) && $validated['expires_at'] === '') {
                $validated['expires_at'] = null;
            }
            
            // If published_at is a date string without time, set it to start of day
            if (isset($validated['published_at']) && $validated['published_at']) {
                $validated['published_at'] = date('Y-m-d H:i:s', strtotime($validated['published_at']));
            }
            // If expires_at is a date string without time, set it to end of day
            if (isset($validated['expires_at']) && $validated['expires_at']) {
                $validated['expires_at'] = date('Y-m-d 23:59:59', strtotime($validated['expires_at']));
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('announcements', $imageName, 'public');
                $validated['image'] = $imagePath;
            }

            $announcement = Announcement::create($validated);
            
            // Load the announcement with image URL
            $announcement->image = $announcement->image ? Storage::url($announcement->image) : null;

            return response()->json([
                'success' => true,
                'message' => 'Announcement created successfully',
                'data' => $announcement
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating announcement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to create announcement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified announcement.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $announcement = Announcement::find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'error' => 'Announcement not found'
                ], 404);
            }

            // Check if user can view inactive announcements
            if (!$announcement->is_active && (!$request->user() || $request->user()->role !== 'admin')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Announcement not found'
                ], 404);
            }

            $data = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'image' => $announcement->image ? Storage::url($announcement->image) : null,
                'published_at' => $announcement->published_at?->toDateString(),
                'expires_at' => $announcement->expires_at?->toDateString(),
                'is_active' => $announcement->is_active,
                'priority' => $announcement->priority,
                'created_at' => $announcement->created_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching announcement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch announcement'
            ], 500);
        }
    }

    /**
     * Update the specified announcement (admin only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            // Check if user is admin
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only admins can update announcements.'
                ], 403);
            }

            $announcement = Announcement::find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'error' => 'Announcement not found'
                ], 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'content' => 'sometimes|string',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'published_at' => 'nullable|date',
                'expires_at' => 'nullable|date|after:published_at',
                'is_active' => 'sometimes|boolean',
                'priority' => 'sometimes|integer|min:0|max:100',
                '_method' => 'sometimes|string',
            ]);

            // Convert empty strings to null for date fields
            if (isset($validated['published_at']) && $validated['published_at'] === '') {
                $validated['published_at'] = null;
            }
            if (isset($validated['expires_at']) && $validated['expires_at'] === '') {
                $validated['expires_at'] = null;
            }
            
            // If published_at is a date string without time, set it to start of day
            if (isset($validated['published_at']) && $validated['published_at']) {
                $validated['published_at'] = date('Y-m-d H:i:s', strtotime($validated['published_at']));
            }
            // If expires_at is a date string without time, set it to end of day
            if (isset($validated['expires_at']) && $validated['expires_at']) {
                $validated['expires_at'] = date('Y-m-d 23:59:59', strtotime($validated['expires_at']));
            }

            // Handle image upload if new image is provided
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($announcement->image && Storage::disk('public')->exists($announcement->image)) {
                    Storage::disk('public')->delete($announcement->image);
                }

                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('announcements', $imageName, 'public');
                $validated['image'] = $imagePath;
            }

            // Remove _method from validated data
            unset($validated['_method']);

            $announcement->update($validated);
            $announcement->refresh();
            
            // Load the announcement with image URL
            $announcement->image = $announcement->image ? Storage::url($announcement->image) : null;

            return response()->json([
                'success' => true,
                'message' => 'Announcement updated successfully',
                'data' => $announcement
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating announcement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to update announcement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified announcement (admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // Check if user is admin
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only admins can delete announcements.'
                ], 403);
            }

            $announcement = Announcement::find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'error' => 'Announcement not found'
                ], 404);
            }

            // Delete associated image if exists
            if ($announcement->image && Storage::disk('public')->exists($announcement->image)) {
                Storage::disk('public')->delete($announcement->image);
            }

            $announcement->delete();

            return response()->json([
                'success' => true,
                'message' => 'Announcement deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting announcement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete announcement: ' . $e->getMessage()
            ], 500);
        }
    }
}

