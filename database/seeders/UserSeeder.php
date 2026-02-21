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
        $platformAdmin = User::create([
            'name' => 'User Keuangan',
            'organization_name' => 'Platform Keuangan',
            'email' => 'user@keuangan.test',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_platform_admin' => true,
            'permissions' => null,
            'account_status' => User::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        $platformAdmin->update(['data_owner_user_id' => $platformAdmin->id]);

        // Optional: user dummy tambahan
        $userKedua = User::create([
            'name' => 'User Kedua',
            'organization_name' => 'Komunitas Kedua',
            'email' => 'user2@keuangan.test',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'is_platform_admin' => false,
            'permissions' => [
                'transactions.manage',
                'bank_accounts.manage',
                'iuran.manage',
                'reports.view',
            ],
            'account_status' => User::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        $userKedua->update(['data_owner_user_id' => $userKedua->id]);
    }
}
