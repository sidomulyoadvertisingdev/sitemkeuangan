<?php

namespace App\Services;

use App\Models\KoperasiLoan;
use App\Models\KoperasiLoanInstallment;
use App\Models\KoperasiSaving;
use App\Models\KoperasiWalletAccount;
use Illuminate\Support\Collection;

class KoperasiWalletService
{
    public function ensureInitialWallets(int $userId): Collection
    {
        $wallets = $this->queryForUser($userId)->orderBy('name')->get();
        if ($wallets->isNotEmpty()) {
            return $wallets;
        }

        foreach (KoperasiWalletAccount::defaultDefinitions() as $definition) {
            KoperasiWalletAccount::withoutGlobalScope('current_user')->create([
                'user_id' => $userId,
                'name' => $definition['name'],
                'wallet_type' => $definition['wallet_type'],
                'opening_balance' => $definition['opening_balance'],
                'is_active' => true,
                'description' => $definition['description'],
            ]);
        }

        return $this->queryForUser($userId)->orderBy('name')->get();
    }

    public function activeWallets(int $userId): Collection
    {
        $this->ensureInitialWallets($userId);

        return $this->queryForUser($userId)
            ->where('is_active', true)
            ->orderByRaw("CASE wallet_type
                WHEN 'modal' THEN 1
                WHEN 'penampungan' THEN 2
                WHEN 'pinjaman' THEN 3
                WHEN 'pendapatan' THEN 4
                WHEN 'operasional' THEN 5
                WHEN 'cadangan' THEN 6
                ELSE 99
            END")
            ->orderBy('name')
            ->get();
    }

    public function allWallets(int $userId): Collection
    {
        $this->ensureInitialWallets($userId);

        return $this->queryForUser($userId)
            ->orderByRaw("CASE wallet_type
                WHEN 'modal' THEN 1
                WHEN 'penampungan' THEN 2
                WHEN 'pinjaman' THEN 3
                WHEN 'pendapatan' THEN 4
                WHEN 'operasional' THEN 5
                WHEN 'cadangan' THEN 6
                ELSE 99
            END")
            ->orderBy('name')
            ->get();
    }

    public function resolveOwnedWallet(?int $walletId, int $userId): ?KoperasiWalletAccount
    {
        if (!$walletId) {
            return null;
        }

        return $this->queryForUser($userId)
            ->where('id', $walletId)
            ->first();
    }

    public function defaultWalletMap(int $userId): array
    {
        $wallets = $this->allWallets($userId);
        $byType = $wallets->groupBy('wallet_type');

        return [
            'saving' => $byType->get(KoperasiWalletAccount::TYPE_HOLDING)?->first()?->id,
            'withdraw' => $byType->get(KoperasiWalletAccount::TYPE_HOLDING)?->first()?->id,
            'loan' => $byType->get(KoperasiWalletAccount::TYPE_LENDING)?->first()?->id
                ?? $byType->get(KoperasiWalletAccount::TYPE_CAPITAL)?->first()?->id,
            'installment_principal' => $byType->get(KoperasiWalletAccount::TYPE_LENDING)?->first()?->id,
            'installment_income' => $byType->get(KoperasiWalletAccount::TYPE_INCOME)?->first()?->id,
        ];
    }

    public function canDelete(KoperasiWalletAccount $wallet): bool
    {
        return !$this->isUsed($wallet);
    }

    public function isUsed(KoperasiWalletAccount $wallet): bool
    {
        return KoperasiSaving::query()->where('wallet_account_id', $wallet->id)->exists()
            || KoperasiLoan::where('wallet_account_id', $wallet->id)->exists()
            || KoperasiLoanInstallment::where('principal_wallet_account_id', $wallet->id)->exists()
            || KoperasiLoanInstallment::where('income_wallet_account_id', $wallet->id)->exists();
    }

    private function queryForUser(int $userId)
    {
        return KoperasiWalletAccount::withoutGlobalScope('current_user')
            ->where('user_id', $userId);
    }
}
