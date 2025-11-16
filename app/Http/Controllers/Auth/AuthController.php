<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{

    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;    
    }

    public function signUpUser(Request $request) : JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email',
                'username' => 'nullable|string',
                'phone' => 'nullable|string',
                'name'  => 'required|string',
                'password'  => 'required|string|min:6',
                'role' => 'nullable|in:admin,customer',
            ]);
            // derive username if not provided
            if (empty($validated['username'])) {
                $validated['username'] = strstr($validated['email'], '@', true) ?: $validated['name'];
            }
            // determine role (only admin can assign admin)
            $role = 'customer';
            if ($request->user() && $request->user()->role === 'admin' && !empty($validated['role'])) {
                $role = $validated['role'];
            }
            $validated['role'] = $role;

            $user = $this->authService->registerAccount($validated);
            return response()->json(['user' => $user],201);
        } catch (\Exception $e) {
            Log::error('error while creating account: '.$e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ],500);
        }
    }

    public function signInUser(Request $request) : JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'password' => "required|string"
            ]);

            $data  = $this->authService->loginUser($validated);
            if(!$data->isNotEmpty()){
                return response()->json(['message' => 'Unauthorized action.'],401);
            }
            
            return response()->json($data,200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ],500);
        }
    }

    public function logout(Request $request) : JsonResponse
    {
        try {
            $request->validate([]);

            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => "logout successfully"],200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }
    
}
