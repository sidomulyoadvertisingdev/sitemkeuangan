<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::where('user_id', auth()->user()->tenantUserId())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('bank-accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('bank-accounts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:100',
            'bank_name'      => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'balance'        => 'nullable|numeric|min:0',
            'is_default'     => 'nullable|boolean',
        ]);

        $account = BankAccount::create([
            'user_id'        => auth()->user()->tenantUserId(),
            'name'           => $request->name,
            'bank_name'      => $request->bank_name,
            'account_number' => $request->account_number,
            'balance'        => $request->balance ?? 0,
            'is_default'     => $request->boolean('is_default'),
        ]);

        if ($account->is_default) {
            $this->unsetOtherDefaults($account->id);
        }

        return redirect()->route('bank-accounts.index')
            ->with('success', 'Rekening berhasil ditambahkan');
    }

    public function edit(BankAccount $bankAccount)
    {
        abort_if($bankAccount->user_id !== auth()->user()->tenantUserId(), 403);

        return view('bank-accounts.edit', compact('bankAccount'));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        abort_if($bankAccount->user_id !== auth()->user()->tenantUserId(), 403);

        $request->validate([
            'name'           => 'required|string|max:100',
            'bank_name'      => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'balance'        => 'nullable|numeric|min:0',
            'is_default'     => 'nullable|boolean',
        ]);

        $bankAccount->update([
            'name'           => $request->name,
            'bank_name'      => $request->bank_name,
            'account_number' => $request->account_number,
            'balance'        => $request->balance ?? $bankAccount->balance,
            'is_default'     => $request->boolean('is_default'),
        ]);

        if ($bankAccount->is_default) {
            $this->unsetOtherDefaults($bankAccount->id);
        }

        return redirect()->route('bank-accounts.index')
            ->with('success', 'Rekening berhasil diperbarui');
    }

    public function destroy(BankAccount $bankAccount)
    {
        abort_if($bankAccount->user_id !== auth()->user()->tenantUserId(), 403);
        $bankAccount->delete();

        return redirect()->route('bank-accounts.index')
            ->with('success', 'Rekening berhasil dihapus');
    }

    public function transferBalance(Request $request)
    {
        $validated = $request->validate([
            'from_bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'to_bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'amount' => 'required|numeric|min:1',
            'transfer_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        $tenantId = auth()->user()->tenantUserId();
        $fromAccount = BankAccount::where('user_id', $tenantId)
            ->where('id', $validated['from_bank_account_id'])
            ->first();
        $toAccount = BankAccount::where('user_id', $tenantId)
            ->where('id', $validated['to_bank_account_id'])
            ->first();

        if (!$fromAccount) {
            throw ValidationException::withMessages([
                'from_bank_account_id' => 'Rekening asal tidak ditemukan.',
            ]);
        }

        if (!$toAccount) {
            throw ValidationException::withMessages([
                'to_bank_account_id' => 'Rekening tujuan tidak ditemukan.',
            ]);
        }

        if ((int) $fromAccount->id === (int) $toAccount->id) {
            throw ValidationException::withMessages([
                'to_bank_account_id' => 'Rekening tujuan harus berbeda dari rekening asal.',
            ]);
        }

        DB::transaction(function () use ($validated, $tenantId, $fromAccount, $toAccount) {
            $fromLocked = BankAccount::where('user_id', $tenantId)
                ->where('id', $fromAccount->id)
                ->lockForUpdate()
                ->first();
            $toLocked = BankAccount::where('user_id', $tenantId)
                ->where('id', $toAccount->id)
                ->lockForUpdate()
                ->first();

            if (!$fromLocked || !$toLocked) {
                throw ValidationException::withMessages([
                    'from_bank_account_id' => 'Rekening tidak valid saat proses pindah saldo.',
                ]);
            }

            $amount = (float) $validated['amount'];
            if ($amount > (float) $fromLocked->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo rekening asal tidak cukup untuk dipindahkan.',
                ]);
            }

            $fromLocked->balance -= $amount;
            $fromLocked->save();

            $toLocked->balance += $amount;
            $toLocked->save();

            AccountTransfer::create([
                'sender_user_id' => $tenantId,
                'receiver_user_id' => $tenantId,
                'sender_bank_account_id' => (int) $fromLocked->id,
                'receiver_bank_account_id' => (int) $toLocked->id,
                'kind' => AccountTransfer::KIND_DIRECT_TRANSFER,
                'status' => AccountTransfer::STATUS_COMPLETED,
                'amount' => $amount,
                'transfer_date' => $validated['transfer_date'],
                'note' => $validated['note'] ?? null,
                'requested_by_user_id' => auth()->id(),
                'processed_by_user_id' => auth()->id(),
                'processed_at' => now(),
            ]);
        });

        return redirect()
            ->route('bank-accounts.index')
            ->with('success', 'Pindah saldo antar rekening berhasil.');
    }

    public function setDefault(BankAccount $bankAccount)
    {
        abort_if($bankAccount->user_id !== auth()->user()->tenantUserId(), 403);
        $bankAccount->update(['is_default' => true]);
        $this->unsetOtherDefaults($bankAccount->id);

        return back()->with('success', 'Rekening utama diperbarui');
    }

    private function unsetOtherDefaults(int $exceptId): void
    {
        BankAccount::where('user_id', auth()->user()->tenantUserId())
            ->where('id', '!=', $exceptId)
            ->update(['is_default' => false]);
    }
}
