<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Debt;
use App\Models\Budget;
use App\Models\Project;
use App\Models\BankAccount;
use App\Models\IuranMember;
use App\Models\IuranInstallment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->user()->tenantUserId();
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

        $iuranTarget = 0;
        $iuranCollected = 0;
        $iuranCollectedMonth = 0;
        $iuranRemaining = 0;
        $iuranProgress = 0;
        $iuranMembers = collect();
        $iuranLunasCount = 0;
        $iuranBelumLunasCount = 0;
        $iuranLunasPercent = 0;
        $iuranBelumLunasPercent = 0;
        $incomeUsaha = (float) $income;
        $incomeIuran = 0.0;
        $saldoUsaha = (float) $incomeUsaha - (float) $expense;
        $totalSaldoDariPemasukan = (float) $incomeUsaha;
        $iuranByMonth = collect();

        if (Schema::hasTable('iuran_members') && Schema::hasTable('iuran_installments')) {
            $iuranTarget = (float) IuranMember::where('user_id', $userId)->sum('target_amount');

            $iuranCollected = (float) IuranInstallment::query()
                ->join('iuran_members', 'iuran_installments.iuran_member_id', '=', 'iuran_members.id')
                ->where('iuran_members.user_id', $userId)
                ->sum('iuran_installments.amount');

            $iuranCollectedMonth = (float) IuranInstallment::query()
                ->join('iuran_members', 'iuran_installments.iuran_member_id', '=', 'iuran_members.id')
                ->where('iuran_members.user_id', $userId)
                ->whereMonth('iuran_installments.paid_at', $month)
                ->whereYear('iuran_installments.paid_at', $year)
                ->sum('iuran_installments.amount');

            $iuranRemaining = max(0, $iuranTarget - $iuranCollected);
            $iuranProgress = $iuranTarget > 0
                ? min(100, round(($iuranCollected / $iuranTarget) * 100))
                : 0;
            $incomeIuran = (float) $iuranCollected;
            $incomeUsaha = max(0, (float) $income - $incomeIuran);
            $saldoUsaha = (float) $incomeUsaha - (float) $expense;
            $totalSaldoDariPemasukan = (float) $incomeUsaha + (float) $incomeIuran;

            $iuranMembers = IuranMember::withSum('installments as paid_amount', 'amount')
                ->where('user_id', $userId)
                ->get()
                ->map(function ($member) {
                    $paid = (float) ($member->paid_amount ?? 0);
                    $target = (float) $member->target_amount;
                    $remaining = max(0, $target - $paid);

                    $member->paid_amount = $paid;
                    $member->remaining_amount = $remaining;
                    $member->progress = $target > 0
                        ? min(100, round(($paid / $target) * 100))
                        : 0;
                    $member->is_completed = $remaining <= 0;

                    return $member;
                })
                // Lunas paling atas, lalu progress terbesar, lalu nama agar urutan stabil.
                ->sort(function ($a, $b) {
                    if ($a->is_completed !== $b->is_completed) {
                        return $a->is_completed ? -1 : 1;
                    }

                    if ($a->progress !== $b->progress) {
                        return $a->progress < $b->progress ? 1 : -1;
                    }

                    return strcmp((string) $a->name, (string) $b->name);
                })
                ->values();

            $iuranLunasCount = (int) $iuranMembers->where('is_completed', true)->count();
            $iuranBelumLunasCount = (int) $iuranMembers->where('is_completed', false)->count();
            $totalIuranMembers = $iuranLunasCount + $iuranBelumLunasCount;
            $iuranLunasPercent = $totalIuranMembers > 0
                ? round(($iuranLunasCount / $totalIuranMembers) * 100)
                : 0;
            $iuranBelumLunasPercent = $totalIuranMembers > 0
                ? round(($iuranBelumLunasCount / $totalIuranMembers) * 100)
                : 0;

            // Di dashboard hanya tampilkan anggota yang sudah lunas.
            $iuranMembers = $iuranMembers
                ->where('is_completed', true)
                ->values();

            $iuranByMonth = IuranInstallment::query()
                ->join('iuran_members', 'iuran_installments.iuran_member_id', '=', 'iuran_members.id')
                ->where('iuran_members.user_id', $userId)
                ->whereYear('iuran_installments.paid_at', $year)
                ->select(
                    DB::raw('MONTH(iuran_installments.paid_at) as month_num'),
                    DB::raw('SUM(iuran_installments.amount) as total_amount')
                )
                ->groupBy(DB::raw('MONTH(iuran_installments.paid_at)'))
                ->get()
                ->mapWithKeys(function ($row) {
                    return [(int) $row->month_num => (float) $row->total_amount];
                });
        }

        // ================= GRAFIK BULANAN (AMAN COLLECTION) =================
        $monthlyRaw = Transaction::where('user_id', $userId)
            ->whereYear('date', $year)
            ->get()
            ->groupBy(function ($item) {
                return (int) date('n', strtotime($item->date)); // 1–12
            });

        $months   = [];
        $incomesUsaha = [];
        $incomesIuran = [];
        $expenses = [];

        for ($m = 1; $m <= 12; $m++) {

            $group = $monthlyRaw->get($m); // ✅ AMAN

            $months[] = date('M', mktime(0, 0, 0, $m, 1));

            $totalIncomePerMonth = $group
                ? $group->where('type', 'income')->sum('amount')
                : 0;
            $iuranIncomePerMonth = (float) ($iuranByMonth->get($m) ?? 0);
            $usahaIncomePerMonth = max(0, (float) $totalIncomePerMonth - $iuranIncomePerMonth);

            $incomesUsaha[] = $usahaIncomePerMonth;
            $incomesIuran[] = $iuranIncomePerMonth;

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
            'incomeUsaha',
            'incomeIuran',
            'saldoUsaha',
            'totalSaldoDariPemasukan',
            'hutang',
            'piutang',
            'iuranTarget',
            'iuranCollected',
            'iuranCollectedMonth',
            'iuranRemaining',
            'iuranProgress',
            'iuranMembers',
            'iuranLunasCount',
            'iuranBelumLunasCount',
            'iuranLunasPercent',
            'iuranBelumLunasPercent',
            'months',
            'incomesUsaha',
            'incomesIuran',
            'expenses',
            'categoryExpense',
            'budgets',
            'projects'
        ));
    }
}
