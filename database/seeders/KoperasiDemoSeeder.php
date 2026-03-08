<?php

namespace Database\Seeders;

use App\Models\KoperasiMember;
use App\Models\KoperasiSaving;
use App\Models\User;
use App\Services\KoperasiWalletService;
use Illuminate\Database\Seeder;

class KoperasiDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner.koperasi@keuangan.test')->first();
        if (!$owner) {
            if ($this->command) {
                $this->command->warn('KoperasiDemoSeeder dilewati: owner koperasi tidak ditemukan.');
            }

            return;
        }

        $tenantId = (int) $owner->tenantUserId();
        $walletService = app(KoperasiWalletService::class);
        $walletService->ensureInitialWallets($tenantId);
        $walletMap = $walletService->defaultWalletMap($tenantId);
        $walletId = (int) ($walletMap['saving'] ?? 0);

        if ($walletId <= 0) {
            if ($this->command) {
                $this->command->warn('KoperasiDemoSeeder dilewati: dompet koperasi default tidak ditemukan.');
            }

            return;
        }

        $members = [
            [
                'member_no' => '24000001',
                'name' => 'Rina Koperasi',
                'nik' => '3175000101010001',
                'gender' => 'perempuan',
                'phone' => '081234560001',
                'address' => 'Jl. Koperasi 1',
                'join_date' => now()->subMonths(5)->toDateString(),
                'status' => 'aktif',
                'note' => 'Anggota demo koperasi mobile',
                'savings' => [
                    ['type' => 'setoran', 'amount' => 450000, 'transaction_date' => now()->subMonths(4)->toDateString(), 'note' => 'Setoran awal'],
                    ['type' => 'setoran', 'amount' => 250000, 'transaction_date' => now()->subMonths(2)->toDateString(), 'note' => 'Setoran rutin'],
                ],
            ],
            [
                'member_no' => '24000002',
                'name' => 'Dedi Koperasi',
                'nik' => '3175000101010002',
                'gender' => 'laki-laki',
                'phone' => '081234560002',
                'address' => 'Jl. Koperasi 2',
                'join_date' => now()->subMonths(3)->toDateString(),
                'status' => 'aktif',
                'note' => 'Anggota demo koperasi mobile',
                'savings' => [
                    ['type' => 'setoran', 'amount' => 300000, 'transaction_date' => now()->subMonths(3)->toDateString(), 'note' => 'Setoran awal'],
                    ['type' => 'penarikan', 'amount' => -100000, 'transaction_date' => now()->subMonth()->toDateString(), 'note' => 'Penarikan kas'],
                ],
            ],
        ];

        foreach ($members as $memberData) {
            $member = KoperasiMember::updateOrCreate(
                [
                    'user_id' => $tenantId,
                    'member_no' => $memberData['member_no'],
                ],
                [
                    'name' => $memberData['name'],
                    'nik' => $memberData['nik'],
                    'gender' => $memberData['gender'],
                    'phone' => $memberData['phone'],
                    'address' => $memberData['address'],
                    'join_date' => $memberData['join_date'],
                    'status' => $memberData['status'],
                    'note' => $memberData['note'],
                ]
            );

            foreach ($memberData['savings'] as $savingData) {
                KoperasiSaving::updateOrCreate(
                    [
                        'koperasi_member_id' => $member->id,
                        'transaction_date' => $savingData['transaction_date'],
                        'type' => $savingData['type'],
                        'amount' => $savingData['amount'],
                    ],
                    [
                        'wallet_account_id' => $walletId,
                        'note' => $savingData['note'],
                    ]
                );
            }
        }

        if ($this->command) {
            $this->command->info('KoperasiDemoSeeder selesai: data koperasi contoh berhasil dibuat.');
        }
    }
}
