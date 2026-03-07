<?php

namespace App\Http\Controllers;

use App\Models\KoperasiWalletAccount;
use App\Services\KoperasiWalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KoperasiWalletController extends Controller
{
    public function __construct(
        private readonly KoperasiWalletService $walletService
    ) {
    }

    public function store(Request $request)
    {
        $userId = auth()->user()->tenantUserId();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'wallet_type' => ['required', 'string', Rule::in(array_keys(KoperasiWalletAccount::typeOptions()))],
            'opening_balance' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        KoperasiWalletAccount::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'wallet_type' => $validated['wallet_type'],
            'opening_balance' => (float) ($validated['opening_balance'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', 'Dompet accounting berhasil ditambahkan.');
    }

    public function update(Request $request, KoperasiWalletAccount $wallet)
    {
        $this->ensureWalletOwner($wallet);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'wallet_type' => ['required', 'string', Rule::in(array_keys(KoperasiWalletAccount::typeOptions()))],
            'opening_balance' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        $wallet->update([
            'name' => $validated['name'],
            'wallet_type' => $validated['wallet_type'],
            'opening_balance' => (float) ($validated['opening_balance'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', 'Dompet accounting berhasil diperbarui.');
    }

    public function destroy(KoperasiWalletAccount $wallet)
    {
        $this->ensureWalletOwner($wallet);

        if (!$this->walletService->canDelete($wallet)) {
            return back()->withErrors([
                'wallet_delete' => 'Dompet tidak bisa dihapus karena sudah dipakai di transaksi koperasi.',
            ]);
        }

        $wallet->delete();

        return back()->with('success', 'Dompet accounting berhasil dihapus.');
    }

    private function ensureWalletOwner(KoperasiWalletAccount $wallet): void
    {
        abort_if((int) $wallet->user_id !== (int) auth()->user()->tenantUserId(), 403);
    }
}
