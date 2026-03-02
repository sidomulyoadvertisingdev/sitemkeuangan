<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPlainPassword = 'password123';
        $defaultPassword = Hash::make($defaultPlainPassword);

        // Platform admin organisasi
        $platformOrganization = User::updateOrCreate(
            ['email' => 'platform-org@keuangan.test'],
            [
                'name' => 'Platform Admin Organisasi',
                'organization_name' => 'Platform Keuangan Pribadi',
                'account_mode' => User::MODE_ORGANIZATION,
                'password' => $defaultPassword,
                'is_admin' => true,
                'is_platform_admin' => true,
                'permissions' => null,
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
            ]
        );
        $this->markSelfAsDataOwner($platformOrganization);

        // Platform admin koperasi
        $platformCooperative = User::updateOrCreate(
            ['email' => 'platform-koperasi@keuangan.test'],
            [
                'name' => 'Platform Admin Koperasi',
                'organization_name' => 'Platform Keuangan Pribadi',
                'account_mode' => User::MODE_COOPERATIVE,
                'password' => $defaultPassword,
                'is_admin' => true,
                'is_platform_admin' => true,
                'permissions' => null,
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
            ]
        );
        $this->markSelfAsDataOwner($platformCooperative);

        // Owner organisasi (non-platform)
        $ownerOrganization = User::updateOrCreate(
            ['email' => 'owner.organisasi@keuangan.test'],
            [
                'name' => 'Owner Organisasi',
                'organization_name' => 'Organisasi Maju Bersama',
                'account_mode' => User::MODE_ORGANIZATION,
                'password' => $defaultPassword,
                'is_admin' => true,
                'is_platform_admin' => false,
                'permissions' => null,
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $platformOrganization->id,
                'invite_quota' => 25,
            ]
        );
        $this->markSelfAsDataOwner($ownerOrganization);

        // Owner koperasi (non-platform)
        $ownerCooperative = User::updateOrCreate(
            ['email' => 'owner.koperasi@keuangan.test'],
            [
                'name' => 'Owner Koperasi',
                'organization_name' => 'Koperasi Sejahtera Bersama',
                'account_mode' => User::MODE_COOPERATIVE,
                'password' => $defaultPassword,
                'is_admin' => true,
                'is_platform_admin' => false,
                'permissions' => null,
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $platformCooperative->id,
                'invite_quota' => 25,
            ]
        );
        $this->markSelfAsDataOwner($ownerCooperative);

        // Petugas penarikan organisasi
        User::updateOrCreate(
            ['email' => 'petugas.organisasi@keuangan.test'],
            [
                'name' => 'Petugas Organisasi',
                'organization_name' => $ownerOrganization->organization_name,
                'account_mode' => User::MODE_ORGANIZATION,
                'password' => $defaultPassword,
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
                'approved_by' => $ownerOrganization->id,
                'data_owner_user_id' => $ownerOrganization->id,
            ]
        );

        // Anggota penabung (organisasi)
        User::updateOrCreate(
            ['email' => 'anggota@keuangan.test'],
            [
                'name' => 'Anggota Penabung',
                'organization_name' => $ownerOrganization->organization_name,
                'account_mode' => User::MODE_ORGANIZATION,
                'password' => $defaultPassword,
                'is_admin' => false,
                'is_platform_admin' => false,
                'permissions' => [
                    'transactions.manage',
                ],
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $ownerOrganization->id,
                'data_owner_user_id' => $ownerOrganization->id,
            ]
        );

        // Petugas penarikan koperasi
        User::updateOrCreate(
            ['email' => 'petugas.koperasi@keuangan.test'],
            [
                'name' => 'Petugas Koperasi',
                'organization_name' => $ownerCooperative->organization_name,
                'account_mode' => User::MODE_COOPERATIVE,
                'password' => $defaultPassword,
                'is_admin' => false,
                'is_platform_admin' => false,
                'permissions' => [
                    'transactions.manage',
                    'bank_accounts.manage',
                    'koperasi.manage',
                    'reports.view',
                ],
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $ownerCooperative->id,
                'data_owner_user_id' => $ownerCooperative->id,
            ]
        );

        // Anggota koperasi
        User::updateOrCreate(
            ['email' => 'anggota.koperasi@keuangan.test'],
            [
                'name' => 'Anggota Koperasi',
                'organization_name' => $ownerCooperative->organization_name,
                'account_mode' => User::MODE_COOPERATIVE,
                'password' => $defaultPassword,
                'is_admin' => false,
                'is_platform_admin' => false,
                'permissions' => [
                    'transactions.manage',
                ],
                'account_status' => User::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $ownerCooperative->id,
                'data_owner_user_id' => $ownerCooperative->id,
            ]
        );

        // Akun pending approval
        User::updateOrCreate(
            ['email' => 'pending@keuangan.test'],
            [
                'name' => 'User Pending',
                'organization_name' => $ownerOrganization->organization_name,
                'account_mode' => User::MODE_ORGANIZATION,
                'password' => $defaultPassword,
                'is_admin' => false,
                'is_platform_admin' => false,
                'permissions' => ['transactions.manage'],
                'account_status' => User::STATUS_PENDING,
                'approved_at' => null,
                'approved_by' => null,
                'data_owner_user_id' => $ownerOrganization->id,
                'banned_at' => null,
                'banned_reason' => null,
            ]
        );

        // Akun banned
        User::updateOrCreate(
            ['email' => 'banned@keuangan.test'],
            [
                'name' => 'User Banned',
                'organization_name' => $ownerOrganization->organization_name,
                'account_mode' => User::MODE_ORGANIZATION,
                'password' => $defaultPassword,
                'is_admin' => false,
                'is_platform_admin' => false,
                'permissions' => ['transactions.manage'],
                'account_status' => User::STATUS_BANNED,
                'approved_at' => now()->subDays(7),
                'approved_by' => $ownerOrganization->id,
                'data_owner_user_id' => $ownerOrganization->id,
                'banned_at' => now()->subDays(2),
                'banned_reason' => 'Pelanggaran kebijakan penggunaan aplikasi.',
            ]
        );

        $this->printSeedCredentials($defaultPlainPassword);
    }

    private function markSelfAsDataOwner(User $user): void
    {
        if ((int) $user->data_owner_user_id !== (int) $user->id) {
            $user->update(['data_owner_user_id' => $user->id]);
        }
    }

    private function printSeedCredentials(string $plainPassword): void
    {
        if (!$this->command) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Seeder user berhasil dibuat. Kredensial login:');
        $this->command->line('- platform-org@keuangan.test');
        $this->command->line('- platform-koperasi@keuangan.test');
        $this->command->line('- owner.organisasi@keuangan.test');
        $this->command->line('- owner.koperasi@keuangan.test');
        $this->command->line('- petugas.organisasi@keuangan.test');
        $this->command->line('- petugas.koperasi@keuangan.test');
        $this->command->line('- anggota@keuangan.test');
        $this->command->line('- anggota.koperasi@keuangan.test');
        $this->command->line('- pending@keuangan.test');
        $this->command->line('- banned@keuangan.test');
        $this->command->line("Password default semua akun: {$plainPassword}");
        $this->command->newLine();
    }
}
