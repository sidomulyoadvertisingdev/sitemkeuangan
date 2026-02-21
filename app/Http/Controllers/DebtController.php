<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\BankAccount;
use App\Models\Category;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function index()
    {
        $debts = Debt::where('user_id', auth()->user()->tenantUserId())
            ->orderBy('status')
            ->orderBy('due_date')
            ->get();

        return view('debts.index', compact('debts'));
    }

    public function create()
    {
        return view('debts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'     => 'required|in:hutang,piutang',
            'name'     => 'required|string|max:100',
            'amount'   => 'required|numeric|min:1',
            'due_date' => 'nullable|date',
            'note'     => 'nullable|string',
        ]);

        Debt::create([
            'user_id'  => auth()->user()->tenantUserId(),
            'type'     => $request->type,
            'name'     => $request->name,
            'amount'   => $request->amount,
            'due_date' => $request->due_date,
            'note'     => $request->note,
            'status'   => 'belum_lunas',
        ]);

        return redirect()
            ->route('debts.index')
            ->with('success', 'Data hutang/piutang berhasil ditambahkan');
    }

    public function edit(Debt $debt)
    {
        abort(404); // fitur edit dinonaktifkan
    }

    public function show(Debt $debt)
    {
        abort_if($debt->user_id !== auth()->user()->tenantUserId(), 403);
        $debt->load(['installments.bankAccount','installments.category']);

        $paid = $debt->installments->sum('amount');
        $remaining = max(0, $debt->amount - $paid);

        $accounts = BankAccount::where('user_id', auth()->user()->tenantUserId())->get();
        $categories = Category::where('user_id', auth()->user()->tenantUserId())
            ->where('type', $debt->type === 'piutang' ? 'income' : 'expense')
            ->orderBy('name')
            ->get();

        return view('debts.show', compact('debt', 'paid', 'remaining', 'accounts', 'categories'));
    }

    public function update(Request $request, Debt $debt)
    {
        abort(404); // fitur edit dinonaktifkan
    }

    public function storeInstallment(Request $request, Debt $debt)
    {
        abort_if($debt->user_id !== auth()->user()->tenantUserId(), 403);

        $request->validate([
            'amount'          => 'required|numeric|min:1',
            'paid_at'         => 'required|date',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'category_id'     => 'required|exists:categories,id',
            'note'            => 'nullable|string',
        ]);

        $bank = BankAccount::where('id', $request->bank_account_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->firstOrFail();

        $category = Category::where('id', $request->category_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->where('type', $debt->type === 'piutang' ? 'income' : 'expense')
            ->firstOrFail();

        $installment = DebtInstallment::create([
            'debt_id'         => $debt->id,
            'bank_account_id' => $bank->id,
            'category_id'     => $category->id,
            'amount'          => $request->amount,
            'paid_at'         => $request->paid_at,
            'note'            => $request->note,
        ]);

        // Catat ke transaksi umum + update saldo rekening
        $txnType = $debt->type === 'piutang' ? 'income' : 'expense';

        \App\Models\Transaction::create([
            'user_id'        => auth()->user()->tenantUserId(),
            'type'           => $txnType,
            'category_id'    => $category->id,
            'project_id'     => null,
            'bank_account_id'=> $bank->id,
            'amount'         => $installment->amount,
            'date'           => $installment->paid_at,
            'note'           => $installment->note ?? ('Pembayaran ' . $debt->name),
        ]);

        // Update saldo rekening secara incremental
        $this->adjustBankBalance($bank, $txnType === 'income'
            ? $installment->amount
            : -$installment->amount);

        if (($debt->remaining - $request->amount) <= 0) {
            $debt->update(['status' => 'lunas']);
        }

        return back()->with('success', 'Cicilan berhasil dicatat');
    }

    public function destroy(Debt $debt)
    {
        abort_if($debt->user_id !== auth()->user()->tenantUserId(), 403);

        $debt->delete();

        return redirect()
            ->route('debts.index')
            ->with('success', 'Data berhasil dihapus');
    }

    private function adjustBankBalance(\App\Models\BankAccount $bank, float $delta): void
    {
        $bank->balance += $delta;
        $bank->save();
    }
}
