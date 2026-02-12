<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // User utama (admin / owner)
        User::create([
            'name' => 'User Keuangan',
            'email' => 'user@keuangan.test',
            'password' => Hash::make('password'),
        ]);

        // Optional: user dummy tambahan
        User::create([
            'name' => 'User Kedua',
            'email' => 'user2@keuangan.test',
            'password' => Hash::make('password'),
        ]);
    }
}
