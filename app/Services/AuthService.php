<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
        $data['username'] = Hash::make($data['password']);
        $newUser = User::create([
            'email' => $data['email'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'name' => $data['name'],
            'password' => $data['password'],
            'role' => 'customer'
        ]);
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
