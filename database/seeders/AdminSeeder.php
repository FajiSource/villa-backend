<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $admin = User::create([
            'email' => 'admin@gmail.com',
            'username' => 'Admin',
            'phone' => null,
            'name' => 'Villa Admin',
            'password' => "Admin123",
            'role' => 'admin'
        ]);
        $admin->assignRole('admin');
        $admin->save();
    }
}
