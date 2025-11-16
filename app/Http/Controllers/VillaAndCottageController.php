<?php

namespace App\Http\Controllers;

use App\Models\VillaAndCottage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VillaAndCottageController extends Controller
{
    /**
     * Display a listing of villas and cottages.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'nullable|in:Villa,Cottage,Room',
                'status' => 'nullable|in:Available,Booked',
                'search' => 'nullable|string|max:255',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = VillaAndCottage::query();

            // Filter by type if provided
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by name if provided
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $villas = $query->orderBy('created_at', 'desc')->get();

            // Transform data to match frontend format
            $transformed = $villas->map(function ($villa) {
                return [
                    'id' => $villa->id,
                    'name' => $villa->name,
                    'type' => strtolower($villa->type),
                    'description' => $villa->description ?? '',
                    'price' => (float) $villa->price_per_night,
                    'image' => $villa->image ? Storage::url($villa->image) : '',
                    'maxGuests' => $villa->capacity,
                    'amenities' => $villa->amenities ?? [],
                    'status' => $villa->status,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformed
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching villas/cottages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch villas/cottages'
            ], 500);
        }
    }

    /**
     * Store a newly created villa/cottage in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Check if user is admin
            // if (!$request->user() || $request->user()->role !== 'admin') {
            //     return response()->json([
            //         'success' => false,
            //         'error' => 'Unauthorized. Only admins can create villas/cottages.'
            //     ], 403);
            // }

            $validated = $request->validate([
                'type' => 'required|in:Villa,Cottage,Room',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
                'amenities' => 'nullable|array',
                'amenities.*' => 'string|max:255',
                'capacity' => 'required|integer|min:1',
                'price_per_night' => 'required|numeric|min:0',
                'status' => 'required|in:Available,Booked',
            ]);

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('villas', $imageName, 'public');
                $validated['image'] = $imagePath;
            }

            $villa = VillaAndCottage::create($validated);
            
            // Load the villa with image URL
            $villa->image = $villa->image ? Storage::url($villa->image) : '';

            return response()->json([
                'success' => true,
                'message' => 'Villa/Cottage created successfully',
                'data' => $villa
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating villa/cottage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to create villa/cottage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified villa/cottage.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $request->merge(['id' => $id]);
            $request->validate([
                'id' => 'required|integer|min:1',
            ]);

            $villa = VillaAndCottage::find($id);

            if (!$villa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Villa/Cottage not found'
                ], 404);
            }

            // Transform data to match frontend format
            $transformed = [
                'id' => $villa->id,
                'name' => $villa->name,
                'type' => strtolower($villa->type),
                'description' => $villa->description ?? '',
                'price' => (float) $villa->price_per_night,
                'image' => $villa->image ? Storage::url($villa->image) : '',
                'maxGuests' => $villa->capacity,
                'amenities' => $villa->amenities ?? [],
                'status' => $villa->status,
            ];

            return response()->json([
                'success' => true,
                'data' => $transformed
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching villa/cottage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch villa/cottage'
            ], 500);
        }
    }

    /**
     * Update the specified villa/cottage in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $request->merge(['id' => $id]);
            $request->validate([
                'id' => 'required|integer|min:1',
            ]);

            // Check if user is admin
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only admins can update villas/cottages.'
                ], 403);
            }

            $villa = VillaAndCottage::find($id);

            if (!$villa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Villa/Cottage not found'
                ], 404);
            }

            $validated = $request->validate([
                'type' => 'sometimes|in:Villa,Cottage,Room',
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
                'amenities' => 'nullable|array',
                'amenities.*' => 'string|max:255',
                'capacity' => 'sometimes|integer|min:1',
                'price_per_night' => 'sometimes|numeric|min:0',
                'status' => 'sometimes|in:Available,Booked',
            ]);

            // Handle image upload if new image is provided
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($villa->image && Storage::disk('public')->exists($villa->image)) {
                    Storage::disk('public')->delete($villa->image);
                }

                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('villas', $imageName, 'public');
                $validated['image'] = $imagePath;
            }

            $villa->update($validated);
            $villa->refresh();
            
            // Load the villa with image URL
            $villa->image = $villa->image ? Storage::url($villa->image) : '';

            return response()->json([
                'success' => true,
                'message' => 'Villa/Cottage updated successfully',
                'data' => $villa
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating villa/cottage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to update villa/cottage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified villa/cottage from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $request->merge(['id' => $id]);
            $request->validate([
                'id' => 'required|integer|min:1',
            ]);

            // Check if user is admin
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only admins can delete villas/cottages.'
                ], 403);
            }

            $villa = VillaAndCottage::find($id);

            if (!$villa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Villa/Cottage not found'
                ], 404);
            }

            // Check if there are any bookings for this villa/cottage
            if ($villa->bookings()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete villa/cottage with existing bookings'
                ], 400);
            }

            // Delete associated image if exists
            if ($villa->image && Storage::disk('public')->exists($villa->image)) {
                Storage::disk('public')->delete($villa->image);
            }

            $villa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Villa/Cottage deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting villa/cottage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete villa/cottage: ' . $e->getMessage()
            ], 500);
        }
    }
}

