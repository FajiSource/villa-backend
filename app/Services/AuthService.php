<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function registerAccount(array $data): Collection
    {
        $username = $data['username'] ?? (strstr($data['email'], '@', true) ?: $data['name']);
        $role = $data['role'] ?? 'customer';
        $newUser = User::create([
            'email' => $data['email'],
            'username' => $username,
            'phone' => $data['phone'] ?? null,
            'name' => $data['name'],
            // password hashing handled by model cast
            'password' => $data['password'],
            'role' => $role
        ]);
        if (method_exists($newUser, 'assignRole')) {
            $newUser->assignRole($role);
        }
        
        // Create welcome notification for customers
        if ($role === 'customer') {
            try {
                Notification::create([
                    'user_id' => $newUser->id,
                    'title' => 'Welcome to Villa Perez Resort!',
                    'message' => "Welcome {$newUser->name}! Thank you for joining us. Start exploring our luxurious villas and cottages, and book your perfect getaway today!",
                    'type' => 'system',
                    'status' => 'unread',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create welcome notification: ' . $e->getMessage());
            }
        }
        
        return Collection::make($newUser);
    }

    public function loginUser(array $data): Collection
    {
        $user = User::where('email', $data['email'])->first();
        if (!Hash::check($data['password'], $user->password)) {
            return Collection::make([]);
        }
        return Collection::make([
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user'  => $user,
            'token_type'    => 'Bearer'
        ]);
    }
}
