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


