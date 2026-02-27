<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Owner platform untuk mode Organization
        $platformAdmin = User::updateOrCreate(
            ['email' => 'user@keuangan.test'],
            [
                'name' => 'User Keuangan',
                'organization_name' => 'Platform Keuangan',
                'account_mode' => User::MODE_ORGANIZATION,
                'password' => Hash::make('password'),
                'is_admin' => true,
                'is_platform_admin' => true,
                'permissions' => null,
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
            ]
        );
        $platformAdmin->update(['data_owner_user_id' => $platformAdmin->id]);

        // Owner platform untuk mode Cooperative
        $platformCooperative = User::updateOrCreate(
            ['email' => 'koperasi@keuangan.test'],
            [
                'name' => 'Owner Koperasi',
                'organization_name' => 'Platform Koperasi',
                'account_mode' => User::MODE_COOPERATIVE,
                'password' => Hash::make('password'),
                'is_admin' => true,
                'is_platform_admin' => true,
                'permissions' => null,
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
            ]
        );
        $platformCooperative->update(['data_owner_user_id' => $platformCooperative->id]);

        // Optional: user dummy mode Organization
        $userKedua = User::updateOrCreate(
            ['email' => 'user2@keuangan.test'],
            [
                'name' => 'User Kedua',
                'organization_name' => 'Komunitas Kedua',
                'account_mode' => User::MODE_ORGANIZATION,
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
            ]
        );
        $userKedua->update(['data_owner_user_id' => $userKedua->id]);
    }
}
