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

    public function singUpUser(Request $request) : JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email',
                'username' => 'required|string',
                'phone' => 'nullable|string',
                'name'  => 'required|string',
                'password'  => 'required|string',
                
            ]);
            $user = $this->authService->registerAccount($validated);
            return response()->json(['user' => $user],200);
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
                return response()->json(['message' => 'Unauthorized'],401);
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
            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => "logout successfully"],200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }
    
}
