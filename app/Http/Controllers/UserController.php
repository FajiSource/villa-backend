<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $limit = (int) ($request->query('limit', 5));
        $users = User::orderBy('created_at', 'desc')->limit($limit)->get(['id','name','email','created_at']);
        return response()->json([
            'success' => true,
            'data' => $users
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $users = User::orderBy('created_at', 'desc')->get(['id','name','email','role','created_at']);
        return response()->json(['success' => true, 'data' => $users], 200);
    }

    public function admins(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $users = User::where('role', 'admin')
            ->orderBy('created_at', 'desc')
            ->get(['id','name','email','role','created_at']);
        return response()->json(['success' => true, 'data' => $users], 200);
    }

    public function customers(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $users = User::where('role', '!=', 'admin')
            ->orderBy('created_at', 'desc')
            ->get(['id','name','email','role','created_at']);
        return response()->json(['success' => true, 'data' => $users], 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        // Users can only update their own account, unless they're admin
        if ($authUser->id !== $id && $authUser->role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|nullable|string|max:255|unique:users,username,' . $id,
            'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
            'phone' => 'sometimes|nullable|string|max:255',
            'password' => 'sometimes|string|min:6',
        ]);

        // Update user fields
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['username'])) {
            $user->username = $validated['username'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (isset($validated['phone'])) {
            $user->phone = $validated['phone'];
        }
        if (isset($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        // Return user data without password (matching /api/user endpoint format)
        $userData = $user->only(['id', 'name', 'email', 'username', 'phone', 'role', 'created_at', 'updated_at']);

        return response()->json($userData, 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }
        $user->delete();
        return response()->json(['success' => true, 'message' => 'User deleted'], 200);
    }

    private function authorizeAdmin(Request $request): void
    {
        $authUser = $request->user();   
        if (!$authUser || $authUser->role !== 'admin') {
            abort(403, 'Unauthorized');
        }
    }
}


