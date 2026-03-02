<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\IuranInstallment;
use App\Models\IuranMember;
use App\Models\Project;
use App\Models\ProjectIuranAssignment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class IuranDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner.organisasi@keuangan.test')->first();
        if (!$owner) {
            if ($this->command) {
                $this->command->warn('IuranDemoSeeder dilewati: owner organisasi tidak ditemukan.');
            }
            return;
        }

        $tenantId = (int) $owner->tenantUserId();
        $currentYear = (int) now()->year;

        $bankAccount = BankAccount::updateOrCreate(
            [
                'user_id' => $tenantId,
                'name' => 'Kas Iuran Organisasi',
            ],
            [
                'bank_name' => 'Kas Internal',
                'account_number' => 'ORG-IURAN-001',
                'balance' => 0,
                'is_default' => true,
            ]
        );

        $incomeCategory = Category::updateOrCreate(
            [
                'user_id' => $tenantId,
                'name' => 'Iuran Anggota',
            ],
            [
                'type' => 'income',
            ]
        );

        $project = Project::updateOrCreate(
            [
                'user_id' => $tenantId,
                'name' => 'Program Iuran Tahunan ' . $currentYear,
            ],
            [
                'bank_account_id' => $bankAccount->id,
                'description' => 'Program pengumpulan iuran anggota tahunan.',
                'target_amount' => 2400000,
                'start_date' => $currentYear . '-01-01',
                'end_date' => $currentYear . '-12-31',
                'status' => 'ongoing',
            ]
        );

        $officer = User::where('email', 'petugas.organisasi@keuangan.test')->first();

        $members = [
            [
                'name' => 'Budi Santoso',
                'target_amount' => 1200000,
                'status' => 'aktif',
                'note' => 'Iuran tahunan 2026',
                'installments' => [
                    ['amount' => 150000, 'paid_at' => $currentYear . '-01-10', 'note' => 'Setoran Januari'],
                    ['amount' => 200000, 'paid_at' => $currentYear . '-02-14', 'note' => 'Setoran Februari'],
                ],
            ],
            [
                'name' => 'Siti Rahma',
                'target_amount' => 1200000,
                'status' => 'aktif',
                'note' => 'Iuran tahunan 2026',
                'installments' => [
                    ['amount' => 250000, 'paid_at' => $currentYear . '-01-12', 'note' => 'Setoran Januari'],
                    ['amount' => 250000, 'paid_at' => $currentYear . '-02-18', 'note' => 'Setoran Februari'],
                ],
            ],
        ];

        foreach ($members as $memberData) {
            $member = IuranMember::updateOrCreate(
                [
                    'user_id' => $tenantId,
                    'name' => $memberData['name'],
                ],
                [
                    'target_amount' => $memberData['target_amount'],
                    'target_start_year' => $currentYear,
                    'target_end_year' => $currentYear,
                    'status' => $memberData['status'],
                    'note' => $memberData['note'],
                ]
            );

            foreach ($memberData['installments'] as $installmentData) {
                IuranInstallment::updateOrCreate(
                    [
                        'iuran_member_id' => $member->id,
                        'paid_at' => $installmentData['paid_at'],
                        'amount' => $installmentData['amount'],
                    ],
                    [
                        'bank_account_id' => $bankAccount->id,
                        'category_id' => $incomeCategory->id,
                        'note' => $installmentData['note'],
                    ]
                );

                Transaction::updateOrCreate(
                    [
                        'user_id' => $tenantId,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                        'bank_account_id' => $bankAccount->id,
                        'amount' => $installmentData['amount'],
                        'date' => $installmentData['paid_at'],
                    ],
                    [
                        'project_id' => $project->id,
                        'note' => $installmentData['note'] . ' - ' . $memberData['name'],
                    ]
                );
            }

            if ($officer && Schema::hasTable('project_iuran_assignments')) {
                ProjectIuranAssignment::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'iuran_member_id' => $member->id,
                    ],
                    [
                        'officer_user_id' => $officer->id,
                        'assigned_by' => $owner->id,
                        'note' => 'Penugasan otomatis dari IuranDemoSeeder',
                    ]
                );
            }

            $paid = (float) $member->installments()->sum('amount');
            $target = (float) $member->target_amount;
            $member->update([
                'status' => $paid >= $target ? 'lunas' : 'aktif',
            ]);
        }

        $bankBalance = (float) Transaction::query()
            ->where('user_id', $tenantId)
            ->where('bank_account_id', $bankAccount->id)
            ->sum('amount');

        $bankAccount->update(['balance' => $bankBalance]);

        if ($this->command) {
            $this->command->info('IuranDemoSeeder selesai: data iuran contoh berhasil dibuat.');
        }
    }
}
