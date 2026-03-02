<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Support\Collection;

class OfficerWalletService
{
    public function isRestrictedOfficer(User $user): bool
    {
        return !$user->is_admin && !$user->is_platform_admin;
    }

    public function resolveForUser(User $actor): BankAccount
    {
        $tenantId = $actor->tenantUserId();

        $wallet = BankAccount::withoutGlobalScope('current_user')
            ->where('user_id', $tenantId)
            ->where('account_kind', BankAccount::KIND_OFFICER_WALLET)
            ->where('owner_user_id', (int) $actor->id)
            ->first();

        if ($wallet) {
            return $wallet;
        }

        return BankAccount::withoutGlobalScope('current_user')->create([
            'user_id' => $tenantId,
            'account_kind' => BankAccount::KIND_OFFICER_WALLET,
            'owner_user_id' => (int) $actor->id,
            'name' => 'Dompet Petugas - ' . trim((string) $actor->name),
            'bank_name' => 'Dompet Internal',
            'account_number' => null,
            'balance' => 0,
            'is_default' => false,
        ]);
    }

    public function adminAccounts(int $tenantId): Collection
    {
        return BankAccount::withoutGlobalScope('current_user')
            ->where('user_id', $tenantId)
            ->where('account_kind', BankAccount::KIND_GENERAL)
            ->whereNull('owner_user_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}

