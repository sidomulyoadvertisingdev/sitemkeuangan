<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Debt;
use App\Models\Budget;
use App\Models\Project;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $year   = now()->year;
        $month  = now()->month;

        // ================= RINGKASAN =================
        $income = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->sum('amount');

        $expense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->sum('amount');

        // SALDO AKUN (AGREGASI BANK ACCOUNT)
        $accounts = BankAccount::where('user_id', $userId)->get();
        $saldo = $accounts->sum('balance');

        $hutang = Debt::where('user_id', $userId)
            ->where('type', 'hutang')
            ->where('status', 'belum_lunas')
            ->sum('amount');

        $piutang = Debt::where('user_id', $userId)
            ->where('type', 'piutang')
            ->where('status', 'belum_lunas')
            ->sum('amount');

        // ================= GRAFIK BULANAN (AMAN COLLECTION) =================
        $monthlyRaw = Transaction::where('user_id', $userId)
            ->whereYear('date', $year)
            ->get()
            ->groupBy(function ($item) {
                return (int) date('n', strtotime($item->date)); // 1–12
            });

        $months   = [];
        $incomes  = [];
        $expenses = [];

        for ($m = 1; $m <= 12; $m++) {

            $group = $monthlyRaw->get($m); // ✅ AMAN

            $months[] = date('M', mktime(0, 0, 0, $m, 1));

            $incomes[] = $group
                ? $group->where('type', 'income')->sum('amount')
                : 0;

            $expenses[] = $group
                ? $group->where('type', 'expense')->sum('amount')
                : 0;
        }

        // ================= PROYEK =================
        $projects = Project::with('transactions')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($project) {
                $allocated = $project->transactions->whereIn('type', ['allocation', 'transfer_in'])->sum('amount');
                $expenses  = $project->transactions->where('type', 'expense')->sum('amount');
                $refunds   = $project->transactions->where('type', 'refund')->sum('amount');
                $netSpent  = $expenses - $refunds;
                $progress  = $project->target_amount > 0
                    ? min(100, round(($netSpent / $project->target_amount) * 100))
                    : 0;

                $project->allocated = $allocated;
                $project->spent     = $netSpent;
                $project->balance   = $allocated - $netSpent;
                $project->progress  = $progress;
                return $project;
            });

        // ================= GRAFIK KATEGORI =================
        $categoryExpense = Transaction::where('transactions.user_id', $userId)
            ->where('transactions.type', 'expense')
            ->whereMonth('transactions.date', $month)
            ->whereYear('transactions.date', $year)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category',
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        // ================= REMINDER BUDGET =================
        $budgets = Budget::with('category')
            ->where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->get()
            ->map(function ($budget) use ($userId, $month, $year) {

                $used = Transaction::where('user_id', $userId)
                    ->where('type', 'expense')
                    ->where('category_id', $budget->category_id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->sum('amount');

                $budget->used = $used;
                $budget->remaining = $budget->limit - $used;
                $budget->percent = $budget->limit > 0
                    ? round(($used / $budget->limit) * 100)
                    : 0;

                return $budget;
            });

        return view('dashboard.index', compact(
            'saldo',
            'income',
            'expense',
            'hutang',
            'piutang',
            'months',
            'incomes',
            'expenses',
            'categoryExpense',
            'budgets',
            'projects'
        ));
    }
}
