<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Budget;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with(['category','project','bankAccount'])
            ->where('user_id', auth()->user()->tenantUserId())
            ->latest()
            ->get();

        return view('transactions.index', compact('transactions'));
    }

    public function create()
    {
        $accounts = \App\Models\BankAccount::where('user_id', auth()->user()->tenantUserId())->get();
        $projects = \App\Models\Project::where('user_id', auth()->user()->tenantUserId())->get();
        // Kategori di-load via AJAX berdasarkan type
        return view('transactions.create', compact('accounts', 'projects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'        => 'required|in:income,expense',
            'category_id' => 'required|exists:categories,id',
            'amount'      => 'required|numeric|min:1',
            'date'        => 'required|date',
            'note'        => 'nullable|string',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'project_id'  => 'nullable|exists:projects,id',
        ]);

        // ================= VALIDASI KATEGORI MILIK USER =================
        $category = Category::where('id', $request->category_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->firstOrFail();

        // ================= VALIDASI REKENING & PROYEK =================
        $bankAccount = BankAccount::where('id', $request->bank_account_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->firstOrFail();

        $project = null;
        if ($request->filled('project_id')) {
            $project = \App\Models\Project::where('id', $request->project_id)
                ->where('user_id', auth()->user()->tenantUserId())
                ->firstOrFail();
        }

        // ================= LOGIKA BUDGET (KHUSUS EXPENSE) =================
        if ($request->type === 'expense') {

            // Ambil budget sesuai kategori & periode
            $budget = Budget::where('user_id', auth()->user()->tenantUserId())
                ->where('category_id', $category->id)
                ->where('month', date('n', strtotime($request->date)))
                ->where('year', date('Y', strtotime($request->date)))
                ->first();

            if ($budget) {
                // Total pengeluaran saat ini
                $used = Transaction::where('user_id', auth()->user()->tenantUserId())
                    ->where('type', 'expense')
                    ->where('category_id', $category->id)
                    ->whereMonth('date', $budget->month)
                    ->whereYear('date', $budget->year)
                    ->sum('amount');

                // Cek over budget
                if (($used + $request->amount) > $budget->limit) {
                    return back()
                        ->withInput()
                        ->withErrors([
                            'amount' => 'Pengeluaran melebihi limit budget kategori ini'
                        ]);
                }
            }
        }

        // ================= SIMPAN TRANSAKSI =================
        $txn = Transaction::create([
            'user_id'     => auth()->user()->tenantUserId(),
            'type'        => $request->type,
            'category_id' => $category->id,
            'project_id'  => $project?->id,
            'bank_account_id' => $bankAccount->id,
            'amount'      => $request->amount,
            'date'        => $request->date,
            'note'        => $request->note,
        ]);

        // Update saldo rekening secara incremental (boleh negatif)
        $this->adjustBankBalance($bankAccount, $request->type === 'income'
            ? $request->amount
            : -$request->amount);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaksi berhasil ditambahkan');
    }

    public function edit(Transaction $transaction)
    {
        abort_if($transaction->user_id !== auth()->user()->tenantUserId(), 403);

        $categories = Category::where('user_id', auth()->user()->tenantUserId())
            ->where('type', $transaction->type)
            ->get();

        $accounts = \App\Models\BankAccount::where('user_id', auth()->user()->tenantUserId())->get();
        $projects = \App\Models\Project::where('user_id', auth()->user()->tenantUserId())->get();

        return view('transactions.edit', compact('transaction', 'categories','accounts','projects'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort_if($transaction->user_id !== auth()->user()->tenantUserId(), 403);

        $request->validate([
            'type'        => 'required|in:income,expense',
            'category_id' => 'required|exists:categories,id',
            'amount'      => 'required|numeric|min:1',
            'date'        => 'required|date',
            'note'        => 'nullable|string',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'project_id'  => 'nullable|exists:projects,id',
        ]);

        $category = Category::where('id', $request->category_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->firstOrFail();

        $bankAccount = BankAccount::where('id', $request->bank_account_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->firstOrFail();

        $project = null;
        if ($request->filled('project_id')) {
            $project = \App\Models\Project::where('id', $request->project_id)
                ->where('user_id', auth()->user()->tenantUserId())
                ->firstOrFail();
        }

        // ================= LOGIKA BUDGET SAAT UPDATE (EXPENSE) =================
        if ($request->type === 'expense') {

            $budget = Budget::where('user_id', auth()->user()->tenantUserId())
                ->where('category_id', $category->id)
                ->where('month', date('n', strtotime($request->date)))
                ->where('year', date('Y', strtotime($request->date)))
                ->first();

            if ($budget) {
                $used = Transaction::where('user_id', auth()->user()->tenantUserId())
                    ->where('type', 'expense')
                    ->where('category_id', $category->id)
                    ->whereMonth('date', $budget->month)
                    ->whereYear('date', $budget->year)
                    ->where('id', '!=', $transaction->id) // exclude transaksi ini
                    ->sum('amount');

                if (($used + $request->amount) > $budget->limit) {
                    return back()
                        ->withInput()
                        ->withErrors([
                            'amount' => 'Update transaksi melebihi limit budget kategori ini'
                        ]);
                }
            }
        }

        $oldBankId = $transaction->bank_account_id;
        $oldType   = $transaction->type;
        $oldAmount = $transaction->amount;

        $transaction->update([
            'type'        => $request->type,
            'category_id' => $category->id,
            'project_id'  => $project?->id,
            'bank_account_id' => $bankAccount->id,
            'amount'      => $request->amount,
            'date'        => $request->date,
            'note'        => $request->note,
        ]);

        // Balikkan efek transaksi lama
        $oldBank = BankAccount::find($oldBankId);
        if ($oldBank) {
            $this->adjustBankBalance($oldBank, $oldType === 'income'
                ? -$oldAmount
                : $oldAmount);
        }

        // Terapkan efek transaksi baru
        $this->adjustBankBalance($bankAccount, $request->type === 'income'
            ? $request->amount
            : -$request->amount);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaksi berhasil diperbarui');
    }

    public function destroy(Transaction $transaction)
    {
        abort_if($transaction->user_id !== auth()->user()->tenantUserId(), 403);

        $bankId = $transaction->bank_account_id;
        $type   = $transaction->type;
        $amount = $transaction->amount;
        $transaction->delete();

        if ($bankId) {
            $bank = BankAccount::find($bankId);
            if ($bank) {
                $this->adjustBankBalance($bank, $type === 'income'
                    ? -$amount
                    : $amount);
            }
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaksi berhasil dihapus');
    }

    /**
     * Hitung ulang saldo rekening berdasarkan seluruh transaksi.
     * Saldo bisa menjadi minus jika pengeluaran melebihi pemasukan.
     */
    private function adjustBankBalance(BankAccount $bank, float $delta): void
    {
        $bank->balance += $delta;
        $bank->save();
    }
}
