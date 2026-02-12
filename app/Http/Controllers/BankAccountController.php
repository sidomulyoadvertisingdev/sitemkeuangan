<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::where('user_id', auth()->id())
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
            'user_id'        => auth()->id(),
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
        abort_if($bankAccount->user_id !== auth()->id(), 403);

        return view('bank-accounts.edit', compact('bankAccount'));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        abort_if($bankAccount->user_id !== auth()->id(), 403);

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
        abort_if($bankAccount->user_id !== auth()->id(), 403);
        $bankAccount->delete();

        return redirect()->route('bank-accounts.index')
            ->with('success', 'Rekening berhasil dihapus');
    }

    public function setDefault(BankAccount $bankAccount)
    {
        abort_if($bankAccount->user_id !== auth()->id(), 403);
        $bankAccount->update(['is_default' => true]);
        $this->unsetOtherDefaults($bankAccount->id);

        return back()->with('success', 'Rekening utama diperbarui');
    }

    private function unsetOtherDefaults(int $exceptId): void
    {
        BankAccount::where('user_id', auth()->id())
            ->where('id', '!=', $exceptId)
            ->update(['is_default' => false]);
    }
}
