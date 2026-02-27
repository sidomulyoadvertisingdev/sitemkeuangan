<?php

namespace App\Http\Controllers;

use App\Models\KoperasiLoan;
use App\Models\KoperasiLoanInstallment;
use App\Models\KoperasiMember;
use App\Models\KoperasiSaving;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KoperasiController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        [$members, $summary] = $this->getReportData($q);

        return view('koperasi.index', compact('members', 'summary', 'q'));
    }

    public function dashboard()
    {
        $userId = auth()->user()->tenantUserId();
        $year = (int) now()->year;

        $members = KoperasiMember::query()
            ->where('user_id', $userId)
            ->withSum('savings as savings_total', 'amount')
            ->with([
                'loans' => function ($query) {
                    $query->with([
                        'installments' => function ($installments) {
                            $installments->orderBy('installment_no')->orderBy('paid_at');
                        },
                    ])
                        ->withSum('installments as paid_principal', 'amount_principal')
                        ->withSum('installments as paid_interest', 'amount_interest');
                },
            ])
            ->orderBy('name')
            ->get();

        $memberAnalytics = $members->map(function ($member) {
            $installmentCount = 0;
            $onTimeCount = 0;
            $lateCount = 0;
            $shortfallTotal = 0.0;
            $loanDisbursed = 0.0;
            $loanOutstanding = 0.0;

            foreach ($member->loans as $loan) {
                $loanDisbursed += (float) $loan->principal_amount;
                $loanOutstanding += max(0, $this->loanTotalBill($loan) - $this->loanTotalPaid($loan));

                foreach ($loan->installments as $installment) {
                    $installmentCount++;
                    $dueDate = Carbon::parse($loan->disbursed_at)
                        ->addMonthsNoOverflow(max(1, (int) $installment->installment_no))
                        ->endOfDay();
                    $paidAt = Carbon::parse($installment->paid_at)->endOfDay();

                    if ($paidAt->lte($dueDate)) {
                        $onTimeCount++;
                    } else {
                        $lateCount++;
                    }

                    $shortfallTotal += (float) ($installment->shortfall_amount ?? 0);
                }
            }

            $onTimeRate = $installmentCount > 0
                ? round(($onTimeCount / $installmentCount) * 100, 1)
                : null;

            return [
                'id' => $member->id,
                'member_no' => (string) $member->member_no,
                'name' => (string) $member->name,
                'status' => (string) $member->status,
                'savings_total' => (float) ($member->savings_total ?? 0),
                'loan_disbursed' => $loanDisbursed,
                'loan_outstanding' => $loanOutstanding,
                'loan_count' => (int) $member->loans->count(),
                'installment_count' => $installmentCount,
                'on_time_count' => $onTimeCount,
                'late_count' => $lateCount,
                'on_time_rate' => $onTimeRate,
                'shortfall_total' => round($shortfallTotal, 2),
                'is_never_late' => $installmentCount > 0 && $lateCount === 0,
                'is_always_exact' => $installmentCount > 0 && $shortfallTotal <= 0.009,
            ];
        });

        $membersWithInstallments = $memberAnalytics->where('installment_count', '>', 0);
        $membersNoInstallmentsCount = (int) $memberAnalytics->where('installment_count', 0)->count();
        $neverLateCount = (int) $membersWithInstallments->where('is_never_late', true)->count();
        $alwaysExactCount = (int) $membersWithInstallments->where('is_always_exact', true)->count();
        $lateMemberCount = (int) $membersWithInstallments->where('late_count', '>', 0)->count();
        $shortfallMemberCount = (int) $membersWithInstallments->where('shortfall_total', '>', 0)->count();
        $avgOnTimeRate = $membersWithInstallments->isNotEmpty()
            ? round((float) $membersWithInstallments->avg('on_time_rate'), 1)
            : 0.0;

        $topDisciplineMembers = $membersWithInstallments
            ->where('is_never_late', true)
            ->sort(function ($a, $b) {
                if ($a['installment_count'] === $b['installment_count']) {
                    return strcmp($a['name'], $b['name']);
                }

                return $a['installment_count'] < $b['installment_count'] ? 1 : -1;
            })
            ->take(7)
            ->values();

        $topRiskMembers = $membersWithInstallments
            ->filter(function ($row) {
                return $row['late_count'] > 0 || $row['shortfall_total'] > 0;
            })
            ->sort(function ($a, $b) {
                if ($a['late_count'] !== $b['late_count']) {
                    return $a['late_count'] < $b['late_count'] ? 1 : -1;
                }
                if ($a['shortfall_total'] !== $b['shortfall_total']) {
                    return $a['shortfall_total'] < $b['shortfall_total'] ? 1 : -1;
                }

                return $a['loan_outstanding'] < $b['loan_outstanding'] ? 1 : -1;
            })
            ->take(7)
            ->values();

        $savingsByMonth = KoperasiSaving::query()
            ->whereHas('member', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereYear('transaction_date', $year)
            ->select(
                DB::raw('MONTH(transaction_date) as month_num'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('MONTH(transaction_date)'))
            ->get()
            ->mapWithKeys(function ($row) {
                return [(int) $row->month_num => (float) $row->total];
            });

        $installmentIncomeByMonth = KoperasiLoanInstallment::query()
            ->whereHas('loan.member', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereYear('paid_at', $year)
            ->select(
                DB::raw('MONTH(paid_at) as month_num'),
                DB::raw('SUM(amount_principal + amount_interest + amount_penalty) as total')
            )
            ->groupBy(DB::raw('MONTH(paid_at)'))
            ->get()
            ->mapWithKeys(function ($row) {
                return [(int) $row->month_num => (float) $row->total];
            });

        $loanDisbursedByMonth = KoperasiLoan::query()
            ->whereHas('member', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereYear('disbursed_at', $year)
            ->select(
                DB::raw('MONTH(disbursed_at) as month_num'),
                DB::raw('SUM(principal_amount) as total')
            )
            ->groupBy(DB::raw('MONTH(disbursed_at)'))
            ->get()
            ->mapWithKeys(function ($row) {
                return [(int) $row->month_num => (float) $row->total];
            });

        $months = [];
        $monthlyIncome = [];
        $monthlyExpense = [];
        $monthlyNet = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[] = date('M', mktime(0, 0, 0, $month, 1));

            $incomeValue = (float) ($savingsByMonth->get($month) ?? 0)
                + (float) ($installmentIncomeByMonth->get($month) ?? 0);
            $expenseValue = (float) ($loanDisbursedByMonth->get($month) ?? 0);
            $netValue = $incomeValue - $expenseValue;

            $monthlyIncome[] = $incomeValue;
            $monthlyExpense[] = $expenseValue;
            $monthlyNet[] = $netValue;
        }

        $summary = [
            'year' => $year,
            'total_members' => (int) $members->count(),
            'active_members' => (int) $members->where('status', 'aktif')->count(),
            'total_savings' => (float) $memberAnalytics->sum('savings_total'),
            'total_loan_disbursed' => (float) $memberAnalytics->sum('loan_disbursed'),
            'total_loan_outstanding' => (float) $memberAnalytics->sum('loan_outstanding'),
            'total_shortfall' => (float) $memberAnalytics->sum('shortfall_total'),
            'members_with_installments' => (int) $membersWithInstallments->count(),
            'never_late_count' => $neverLateCount,
            'always_exact_count' => $alwaysExactCount,
            'late_member_count' => $lateMemberCount,
            'shortfall_member_count' => $shortfallMemberCount,
            'members_no_installments_count' => $membersNoInstallmentsCount,
            'avg_on_time_rate' => $avgOnTimeRate,
            'total_income_ytd' => (float) array_sum($monthlyIncome),
            'total_expense_ytd' => (float) array_sum($monthlyExpense),
            'net_cashflow_ytd' => (float) array_sum($monthlyNet),
        ];

        $chart = [
            'months' => $months,
            'income' => $monthlyIncome,
            'expense' => $monthlyExpense,
            'net' => $monthlyNet,
            'punctual_member' => [
                $neverLateCount,
                $lateMemberCount,
                $membersNoInstallmentsCount,
            ],
            'payment_quality_member' => [
                $alwaysExactCount,
                $shortfallMemberCount,
                $membersNoInstallmentsCount,
            ],
        ];

        $insights = $this->buildDashboardInsights($summary, $topRiskMembers);

        return view('koperasi.dashboard', compact(
            'summary',
            'chart',
            'insights',
            'topDisciplineMembers',
            'topRiskMembers'
        ));
    }

    public function transactions(Request $request, string $menu)
    {
        $menuKey = str_replace('-', '_', strtolower(trim($menu)));
        abort_unless(in_array($menuKey, ['simpan', 'pinjam', 'withdraw', 'angsuran', 'bagi_hasil'], true), 404);

        $userId = auth()->user()->tenantUserId();
        $q = trim((string) $request->query('q', ''));

        $menuLabel = match ($menuKey) {
            'simpan' => 'Simpan',
            'pinjam' => 'Pinjam',
            'withdraw' => 'Withdraw',
            'angsuran' => 'Angsuran',
            'bagi_hasil' => 'Bagi Hasil',
        };

        $summaryLabel = match ($menuKey) {
            'simpan' => 'Total Simpanan Tercatat',
            'pinjam' => 'Total Pinjaman Dicairkan',
            'withdraw' => 'Total Withdraw Tercatat',
            'angsuran' => 'Total Angsuran Masuk',
            'bagi_hasil' => 'Total Bagi Hasil (Dari Bunga)',
        };

        if ($menuKey === 'simpan') {
            $query = KoperasiSaving::query()
                ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_savings.koperasi_member_id')
                ->where('koperasi_members.user_id', $userId)
                ->where('koperasi_savings.amount', '>=', 0)
                ->select(
                    'koperasi_savings.*',
                    'koperasi_members.member_no',
                    'koperasi_members.name as member_name'
                )
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->where(function ($inner) use ($q) {
                        $inner->where('koperasi_members.member_no', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_members.name', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_savings.type', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_savings.note', 'like', '%' . $q . '%');
                    });
                })
                ->orderByDesc('koperasi_savings.transaction_date')
                ->orderByDesc('koperasi_savings.id');

            $summaryValue = (float) (clone $query)->sum('koperasi_savings.amount');
            $rows = $query->paginate(10)->withQueryString();
        } elseif ($menuKey === 'pinjam') {
            $query = KoperasiLoan::query()
                ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_loans.koperasi_member_id')
                ->where('koperasi_members.user_id', $userId)
                ->select(
                    'koperasi_loans.*',
                    'koperasi_members.member_no',
                    'koperasi_members.name as member_name'
                )
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->where(function ($inner) use ($q) {
                        $inner->where('koperasi_loans.loan_no', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_members.member_no', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_members.name', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_loans.status', 'like', '%' . $q . '%');
                    });
                })
                ->orderByDesc('koperasi_loans.disbursed_at')
                ->orderByDesc('koperasi_loans.id');

            $summaryValue = (float) (clone $query)->sum('koperasi_loans.principal_amount');
            $rows = $query->paginate(10)->withQueryString();
        } elseif ($menuKey === 'withdraw') {
            $query = KoperasiSaving::query()
                ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_savings.koperasi_member_id')
                ->where('koperasi_members.user_id', $userId)
                ->where(function ($inner) {
                    $inner->where('koperasi_savings.amount', '<', 0)
                        ->orWhere('koperasi_savings.note', 'like', '[WD]%')
                        ->orWhere('koperasi_savings.note', 'like', '%withdraw%')
                        ->orWhere('koperasi_savings.note', 'like', '%tarik%');
                })
                ->select(
                    'koperasi_savings.*',
                    'koperasi_members.member_no',
                    'koperasi_members.name as member_name'
                )
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->where(function ($inner) use ($q) {
                        $inner->where('koperasi_members.member_no', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_members.name', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_savings.note', 'like', '%' . $q . '%');
                    });
                })
                ->orderByDesc('koperasi_savings.transaction_date')
                ->orderByDesc('koperasi_savings.id');

            $summaryValue = (float) (clone $query)->sum(DB::raw('ABS(koperasi_savings.amount)'));
            $rows = $query->paginate(10)->withQueryString();
        } elseif ($menuKey === 'angsuran') {
            $query = KoperasiLoanInstallment::query()
                ->join('koperasi_loans', 'koperasi_loans.id', '=', 'koperasi_loan_installments.koperasi_loan_id')
                ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_loans.koperasi_member_id')
                ->where('koperasi_members.user_id', $userId)
                ->select(
                    'koperasi_loan_installments.*',
                    'koperasi_loans.loan_no',
                    'koperasi_members.member_no',
                    'koperasi_members.name as member_name',
                    DB::raw('(koperasi_loan_installments.amount_principal + koperasi_loan_installments.amount_interest + koperasi_loan_installments.amount_penalty) as amount_total')
                )
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->where(function ($inner) use ($q) {
                        $inner->where('koperasi_loans.loan_no', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_members.member_no', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_members.name', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_loan_installments.note', 'like', '%' . $q . '%');
                    });
                })
                ->orderByDesc('koperasi_loan_installments.paid_at')
                ->orderByDesc('koperasi_loan_installments.id');

            $summaryValue = (float) (clone $query)->sum(
                DB::raw('koperasi_loan_installments.amount_principal + koperasi_loan_installments.amount_interest + koperasi_loan_installments.amount_penalty')
            );
            $rows = $query->paginate(10)->withQueryString();
        } else {
            $query = KoperasiLoanInstallment::query()
                ->join('koperasi_loans', 'koperasi_loans.id', '=', 'koperasi_loan_installments.koperasi_loan_id')
                ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_loans.koperasi_member_id')
                ->where('koperasi_members.user_id', $userId)
                ->where('koperasi_loan_installments.amount_interest', '>', 0)
                ->select(
                    'koperasi_loan_installments.*',
                    'koperasi_loans.loan_no',
                    'koperasi_members.member_no',
                    'koperasi_members.name as member_name'
                )
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->where(function ($inner) use ($q) {
                        $inner->where('koperasi_loans.loan_no', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_members.member_no', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_members.name', 'like', '%' . $q . '%')
                            ->orWhere('koperasi_loan_installments.note', 'like', '%' . $q . '%');
                    });
                })
                ->orderByDesc('koperasi_loan_installments.paid_at')
                ->orderByDesc('koperasi_loan_installments.id');

            $summaryValue = (float) (clone $query)->sum('koperasi_loan_installments.amount_interest');
            $rows = $query->paginate(10)->withQueryString();
        }

        $memberReferences = KoperasiMember::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'member_no', 'nik', 'name', 'phone', 'status']);

        $loanReferences = KoperasiLoan::query()
            ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_loans.koperasi_member_id')
            ->where('koperasi_members.user_id', $userId)
            ->select(
                'koperasi_loans.id',
                'koperasi_loans.loan_no',
                'koperasi_loans.status',
                'koperasi_members.member_no',
                'koperasi_members.nik',
                'koperasi_members.name as member_name'
            )
            ->orderByDesc('koperasi_loans.id')
            ->get();

        return view('koperasi.transactions', compact(
            'menuKey',
            'menuLabel',
            'summaryLabel',
            'summaryValue',
            'rows',
            'q',
            'memberReferences',
            'loanReferences'
        ));
    }

    public function exportPdf(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        [$members, $summary] = $this->getReportData($q);

        $lines = [
            'LAPORAN KOPERASI SIMPAN PINJAM',
            'Tanggal Export : ' . date('d-m-Y H:i:s'),
            'User           : ' . auth()->user()->name,
            'Filter Pencarian: ' . ($q !== '' ? $q : '-'),
            '',
            'RINGKASAN',
            'Total Member         : ' . $summary['total_members'],
            'Total Simpanan       : Rp ' . number_format($summary['total_savings'], 0, ',', '.'),
            'Total Pinjaman Cair  : Rp ' . number_format($summary['total_loan_disbursed'], 0, ',', '.'),
            'Total Sisa Pinjaman  : Rp ' . number_format($summary['total_loan_outstanding'], 0, ',', '.'),
            '',
            'DETAIL MEMBER',
        ];

        if ($members->isEmpty()) {
            $lines[] = 'Belum ada data member koperasi.';
        } else {
            foreach ($members->values() as $index => $member) {
                $lines[] = sprintf(
                    '%d. %s | %s | Simp: Rp %s | Cair: Rp %s | Sisa: Rp %s | %s',
                    $index + 1,
                    $this->truncateText((string) $member->member_no, 20),
                    $this->truncateText((string) $member->name, 24),
                    number_format((float) ($member->savings_total ?? 0), 0, ',', '.'),
                    number_format((float) $member->loan_disbursed, 0, ',', '.'),
                    number_format((float) $member->loan_outstanding, 0, ',', '.'),
                    ucfirst((string) $member->status)
                );
            }
        }

        $pdf = $this->buildSimplePdf($lines);
        $filename = 'laporan-koperasi-' . date('Ymd-His') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        [$members, $summary] = $this->getReportData($q);

        $xml = $this->buildExcelXml($members, $summary, $q);
        $filename = 'laporan-koperasi-' . date('Ymd-His') . '.xls';

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function create()
    {
        return view('koperasi.create');
    }

    public function store(Request $request)
    {
        $userId = auth()->user()->tenantUserId();

        $request->validate([
            'name' => 'required|string|max:120',
            'nik' => 'nullable|string|max:30',
            'gender' => 'nullable|in:L,P',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'join_date' => 'required|date',
            'status' => 'required|in:aktif,nonaktif',
            'note' => 'nullable|string',
        ]);

        $member = KoperasiMember::create([
            'user_id' => $userId,
            'name' => $request->name,
            'nik' => $request->nik,
            'gender' => $request->gender,
            'phone' => $request->phone,
            'address' => $request->address,
            'join_date' => $request->join_date,
            'status' => $request->status,
            'note' => $request->note,
        ]);

        return redirect()
            ->route('koperasi.index')
            ->with('success', 'Member koperasi berhasil ditambahkan. No Rekening: ' . $member->member_no);
    }

    public function show(KoperasiMember $koperasi)
    {
        $this->ensureMemberOwner($koperasi);

        $savings = $koperasi->savings()
            ->latest('transaction_date')
            ->latest()
            ->get();

        $loans = $koperasi->loans()
            ->with([
                'installments' => function ($query) {
                    $query->latest('paid_at')->latest();
                },
            ])
            ->withSum('installments as paid_principal', 'amount_principal')
            ->withSum('installments as paid_interest', 'amount_interest')
            ->withSum('installments as paid_penalty', 'amount_penalty')
            ->latest('disbursed_at')
            ->latest()
            ->get()
            ->map(function ($loan) {
                $loan->total_bill_value = $this->loanTotalBill($loan);
                $loan->total_paid_value = $this->loanTotalPaid($loan);
                $loan->remaining_value = max(0, $loan->total_bill_value - $loan->total_paid_value);
                $loan->base_installment_value = $this->loanBaseInstallment($loan);
                $loan->next_installment_no = ((int) ($loan->installments->max('installment_no') ?? 0)) + 1;
                $loan->next_expected_value = $this->installmentExpectedAmount(
                    $loan,
                    $loan->next_installment_no,
                    (float) $loan->total_paid_value
                );
                $suggestedAllocation = $this->allocateInstallmentCorePayment($loan, $loan->next_expected_value);
                $loan->suggested_principal_value = $suggestedAllocation['principal'];
                $loan->suggested_interest_value = $suggestedAllocation['interest'];
                $loan->progress = $loan->total_bill_value > 0
                    ? min(100, (int) round(($loan->total_paid_value / $loan->total_bill_value) * 100))
                    : 0;

                return $loan;
            });

        $summary = [
            'total_savings' => (float) $savings->sum('amount'),
            'total_loan_disbursed' => (float) $loans->sum(fn ($loan) => (float) $loan->principal_amount),
            'total_loan_outstanding' => (float) $loans->sum(fn ($loan) => (float) $loan->remaining_value),
        ];

        return view('koperasi.show', compact('koperasi', 'savings', 'loans', 'summary'));
    }

    public function edit(KoperasiMember $koperasi)
    {
        $this->ensureMemberOwner($koperasi);

        return view('koperasi.edit', compact('koperasi'));
    }

    public function update(Request $request, KoperasiMember $koperasi)
    {
        $this->ensureMemberOwner($koperasi);

        $request->validate([
            'name' => 'required|string|max:120',
            'nik' => 'nullable|string|max:30',
            'gender' => 'nullable|in:L,P',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'join_date' => 'required|date',
            'status' => 'required|in:aktif,nonaktif',
            'note' => 'nullable|string',
        ]);

        $koperasi->update([
            'name' => $request->name,
            'nik' => $request->nik,
            'gender' => $request->gender,
            'phone' => $request->phone,
            'address' => $request->address,
            'join_date' => $request->join_date,
            'status' => $request->status,
            'note' => $request->note,
        ]);

        return redirect()
            ->route('koperasi.index')
            ->with('success', 'Data member koperasi berhasil diperbarui.');
    }

    public function destroy(KoperasiMember $koperasi)
    {
        $this->ensureMemberOwner($koperasi);

        if ($koperasi->savings()->exists() || $koperasi->loans()->exists()) {
            return back()->withErrors([
                'delete' => 'Member yang sudah memiliki transaksi simpanan/pinjaman tidak dapat dihapus.',
            ]);
        }

        $koperasi->delete();

        return redirect()
            ->route('koperasi.index')
            ->with('success', 'Member koperasi berhasil dihapus.');
    }

    public function storeSaving(Request $request, KoperasiMember $koperasi)
    {
        $this->ensureMemberOwner($koperasi);

        $request->validate([
            'type' => 'required|in:pokok,wajib,sukarela',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        KoperasiSaving::create([
            'koperasi_member_id' => $koperasi->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'transaction_date' => $request->transaction_date,
            'note' => $request->note,
        ]);

        return back()->with('success', 'Simpanan berhasil dicatat.');
    }

    public function storeSavingFromTransaction(Request $request)
    {
        $userId = auth()->user()->tenantUserId();

        $request->validate([
            'member_account_no' => 'required|digits:8',
            'type' => 'required|in:pokok,wajib,sukarela',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        [$member, $error] = $this->resolveMemberFromAccountNo((string) $request->member_account_no, $userId);
        if (!$member) {
            return back()->withErrors([
                'member_account_no' => $error ?: 'Nomor rekening member tidak ditemukan.',
            ])->withInput();
        }

        KoperasiSaving::create([
            'koperasi_member_id' => $member->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'transaction_date' => $request->transaction_date,
            'note' => $request->note,
        ]);

        return redirect()
            ->route('koperasi.transactions', ['menu' => 'simpan'])
            ->with('success', 'Simpanan berhasil ditambahkan untuk ' . $member->name . '.');
    }

    public function storeLoanFromTransaction(Request $request)
    {
        $userId = auth()->user()->tenantUserId();

        $request->validate([
            'member_account_no' => 'required|digits:8',
            'loan_no' => 'required|string|max:50',
            'principal_amount' => 'required|numeric|min:1',
            'interest_percent' => 'required|numeric|min:0|max:100',
            'admin_fee' => 'required|numeric|min:0',
            'tenor_months' => 'required|integer|min:1|max:240',
            'disbursed_at' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:disbursed_at',
            'status' => 'required|in:berjalan,lunas,macet',
            'note' => 'nullable|string',
        ]);

        [$member, $error] = $this->resolveMemberFromAccountNo((string) $request->member_account_no, $userId);
        if (!$member) {
            return back()->withErrors([
                'member_account_no' => $error ?: 'Nomor rekening member tidak ditemukan.',
            ])->withInput();
        }

        $loanExists = KoperasiLoan::query()
            ->where('koperasi_member_id', $member->id)
            ->where('loan_no', $request->loan_no)
            ->exists();

        if ($loanExists) {
            return back()->withErrors([
                'loan_no' => 'No pinjaman sudah dipakai untuk member ini.',
            ])->withInput();
        }

        KoperasiLoan::create([
            'koperasi_member_id' => $member->id,
            'loan_no' => $request->loan_no,
            'principal_amount' => $request->principal_amount,
            'interest_percent' => $request->interest_percent,
            'admin_fee' => $request->admin_fee,
            'tenor_months' => $request->tenor_months,
            'disbursed_at' => $request->disbursed_at,
            'due_date' => $request->due_date,
            'status' => $request->status,
            'note' => $request->note,
        ]);

        return redirect()
            ->route('koperasi.transactions', ['menu' => 'pinjam'])
            ->with('success', 'Pinjaman berhasil ditambahkan untuk ' . $member->name . '.');
    }

    public function storeWithdrawFromTransaction(Request $request)
    {
        $userId = auth()->user()->tenantUserId();

        $request->validate([
            'member_account_no' => 'required|digits:8',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        [$member, $error] = $this->resolveMemberFromAccountNo((string) $request->member_account_no, $userId);
        if (!$member) {
            return back()->withErrors([
                'member_account_no' => $error ?: 'Nomor rekening member tidak ditemukan.',
            ])->withInput();
        }

        $note = trim((string) $request->note);
        $noteText = $note !== '' ? '[WD] ' . $note : '[WD] Penarikan simpanan sukarela';

        KoperasiSaving::create([
            'koperasi_member_id' => $member->id,
            'type' => 'sukarela',
            'amount' => -1 * abs((float) $request->amount),
            'transaction_date' => $request->transaction_date,
            'note' => $noteText,
        ]);

        return redirect()
            ->route('koperasi.transactions', ['menu' => 'withdraw'])
            ->with('success', 'Withdraw berhasil dicatat untuk ' . $member->name . '.');
    }

    public function storeInstallmentFromTransaction(Request $request)
    {
        $userId = auth()->user()->tenantUserId();

        $request->validate([
            'member_account_no' => 'required|digits:8',
            'loan_no' => 'nullable|string|max:50',
            'installment_no' => 'nullable|integer|min:1',
            'amount_total' => 'required|numeric|min:0.01',
            'amount_penalty' => 'required|numeric|min:0',
            'paid_at' => 'required|date',
            'note' => 'nullable|string',
        ]);

        [$member, $memberError] = $this->resolveMemberFromAccountNo((string) $request->member_account_no, $userId);
        if (!$member) {
            return back()->withErrors([
                'member_account_no' => $memberError ?: 'Nomor rekening member tidak ditemukan.',
            ])->withInput();
        }

        [$loan, $error] = $this->resolveLoanFromMemberAccount(
            $member,
            trim((string) $request->loan_no)
        );
        if (!$loan) {
            return back()->withErrors([
                'loan_no' => $error ?: 'Pinjaman tidak ditemukan.',
            ])->withInput();
        }

        $amountTotal = (float) $request->amount_total;
        $amountPenalty = (float) $request->amount_penalty;
        $corePayment = $amountTotal;

        $this->refreshLoanSummary($loan);
        $remaining = max(0, $this->loanTotalBill($loan) - $this->loanTotalPaid($loan));
        if ($remaining <= 0) {
            return back()->withErrors([
                'amount_total' => 'Pinjaman ini sudah lunas.',
            ])->withInput();
        }

        if ($corePayment > $remaining) {
            return back()->withErrors([
                'amount_total' => 'Nominal angsuran melebihi sisa pinjaman (Rp ' . number_format($remaining, 0, ',', '.') . ').',
            ])->withInput();
        }

        $installmentNo = (int) ($request->installment_no ?: ($loan->installments()->max('installment_no') + 1));
        if ($loan->installments()->where('installment_no', $installmentNo)->exists()) {
            return back()->withErrors([
                'installment_no' => 'Nomor angsuran sudah dipakai untuk pinjaman ini.',
            ])->withInput();
        }

        $paidBeforeInstallment = (float) ($loan->installments()
            ->where('installment_no', '<', $installmentNo)
            ->selectRaw('COALESCE(SUM(amount_principal + amount_interest), 0) as paid_before')
            ->value('paid_before') ?? 0);

        $expectedAmount = $this->installmentExpectedAmount($loan, $installmentNo, $paidBeforeInstallment);
        $difference = round($corePayment - $expectedAmount, 2);
        $paymentStatus = 'sesuai';
        $shortfallAmount = 0.0;

        if ($difference < -0.009) {
            $paymentStatus = 'kurang_bayar';
            $shortfallAmount = abs($difference);
        } elseif ($difference > 0.009) {
            $paymentStatus = 'lebih_bayar';
        }

        $allocation = $this->allocateInstallmentCorePayment($loan, $corePayment);

        KoperasiLoanInstallment::create([
            'koperasi_loan_id' => $loan->id,
            'installment_no' => $installmentNo,
            'expected_amount' => $expectedAmount,
            'amount_principal' => $allocation['principal'],
            'amount_interest' => $allocation['interest'],
            'amount_penalty' => $amountPenalty,
            'payment_status' => $paymentStatus,
            'shortfall_amount' => $shortfallAmount,
            'paid_at' => $request->paid_at,
            'note' => $request->note,
        ]);

        $this->syncLoanStatus($loan);

        return redirect()
            ->route('koperasi.transactions', ['menu' => 'angsuran'])
            ->with('success', 'Angsuran berhasil dicatat untuk pinjaman ' . $loan->loan_no . '.');
    }

    public function storeWithdraw(Request $request, KoperasiMember $koperasi)
    {
        $this->ensureMemberOwner($koperasi);

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $note = trim((string) $request->note);
        $noteText = $note !== '' ? '[WD] ' . $note : '[WD] Penarikan simpanan sukarela';

        KoperasiSaving::create([
            'koperasi_member_id' => $koperasi->id,
            'type' => 'sukarela',
            'amount' => -1 * abs((float) $request->amount),
            'transaction_date' => $request->transaction_date,
            'note' => $noteText,
        ]);

        return back()->with('success', 'Withdraw simpanan berhasil dicatat.');
    }

    public function storeLoan(Request $request, KoperasiMember $koperasi)
    {
        $this->ensureMemberOwner($koperasi);

        $request->validate([
            'loan_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('koperasi_loans', 'loan_no')->where(function ($query) use ($koperasi) {
                    $query->where('koperasi_member_id', $koperasi->id);
                }),
            ],
            'principal_amount' => 'required|numeric|min:1',
            'interest_percent' => 'required|numeric|min:0|max:100',
            'admin_fee' => 'required|numeric|min:0',
            'tenor_months' => 'required|integer|min:1|max:240',
            'disbursed_at' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:disbursed_at',
            'status' => 'required|in:berjalan,lunas,macet',
            'note' => 'nullable|string',
        ]);

        KoperasiLoan::create([
            'koperasi_member_id' => $koperasi->id,
            'loan_no' => $request->loan_no,
            'principal_amount' => $request->principal_amount,
            'interest_percent' => $request->interest_percent,
            'admin_fee' => $request->admin_fee,
            'tenor_months' => $request->tenor_months,
            'disbursed_at' => $request->disbursed_at,
            'due_date' => $request->due_date,
            'status' => $request->status,
            'note' => $request->note,
        ]);

        return back()->with('success', 'Pinjaman berhasil ditambahkan.');
    }

    public function storeInstallment(Request $request, KoperasiLoan $loan)
    {
        $member = $loan->member;
        abort_if(!$member || $member->user_id !== auth()->user()->tenantUserId(), 403);

        $request->validate([
            'installment_no' => 'nullable|integer|min:1',
            'amount_total' => 'required|numeric|min:0.01',
            'amount_penalty' => 'required|numeric|min:0',
            'paid_at' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $amountTotal = (float) $request->amount_total;
        $amountPenalty = (float) $request->amount_penalty;
        $corePayment = $amountTotal;

        $this->refreshLoanSummary($loan);
        $remaining = max(0, $this->loanTotalBill($loan) - $this->loanTotalPaid($loan));
        if ($remaining <= 0) {
            return back()->withErrors([
                'amount_total' => 'Pinjaman ini sudah lunas.',
            ])->withInput();
        }

        if ($corePayment > $remaining) {
            return back()->withErrors([
                'amount_total' => 'Nominal angsuran melebihi sisa pinjaman (Rp ' . number_format($remaining, 0, ',', '.') . ').',
            ])->withInput();
        }

        $installmentNo = (int) ($request->installment_no ?: ($loan->installments()->max('installment_no') + 1));
        if ($loan->installments()->where('installment_no', $installmentNo)->exists()) {
            return back()->withErrors([
                'installment_no' => 'Nomor angsuran sudah dipakai untuk pinjaman ini.',
            ])->withInput();
        }

        $paidBeforeInstallment = (float) ($loan->installments()
            ->where('installment_no', '<', $installmentNo)
            ->selectRaw('COALESCE(SUM(amount_principal + amount_interest), 0) as paid_before')
            ->value('paid_before') ?? 0);

        $expectedAmount = $this->installmentExpectedAmount($loan, $installmentNo, $paidBeforeInstallment);
        $difference = round($corePayment - $expectedAmount, 2);
        $paymentStatus = 'sesuai';
        $shortfallAmount = 0.0;

        if ($difference < -0.009) {
            $paymentStatus = 'kurang_bayar';
            $shortfallAmount = abs($difference);
        } elseif ($difference > 0.009) {
            $paymentStatus = 'lebih_bayar';
        }

        $allocation = $this->allocateInstallmentCorePayment($loan, $corePayment);

        KoperasiLoanInstallment::create([
            'koperasi_loan_id' => $loan->id,
            'installment_no' => $installmentNo,
            'expected_amount' => $expectedAmount,
            'amount_principal' => $allocation['principal'],
            'amount_interest' => $allocation['interest'],
            'amount_penalty' => $amountPenalty,
            'payment_status' => $paymentStatus,
            'shortfall_amount' => $shortfallAmount,
            'paid_at' => $request->paid_at,
            'note' => $request->note,
        ]);

        $this->syncLoanStatus($loan);

        return back()->with('success', 'Angsuran pinjaman berhasil dicatat.');
    }

    private function ensureMemberOwner(KoperasiMember $member): void
    {
        abort_if($member->user_id !== auth()->user()->tenantUserId(), 403);
    }

    private function resolveMemberFromAccountNo(string $accountNo, int $userId): array
    {
        $accountNo = trim($accountNo);
        if ($accountNo === '') {
            return [null, 'Nomor rekening wajib diisi.'];
        }

        if (!preg_match('/^\d{8}$/', $accountNo)) {
            return [null, 'Nomor rekening harus 8 digit angka.'];
        }

        $member = KoperasiMember::query()
            ->where('user_id', $userId)
            ->where('member_no', $accountNo)
            ->first();
        if ($member) {
            return [$member, null];
        }

        return [null, 'Nomor rekening tidak ditemukan.'];
    }

    private function resolveLoanFromMemberAccount(KoperasiMember $member, string $loanNo = ''): array
    {
        $loanNo = trim($loanNo);

        $activeLoanQuery = KoperasiLoan::query()
            ->where('koperasi_member_id', $member->id)
            ->whereIn('status', ['berjalan', 'macet'])
            ->orderByDesc('id');

        if ($loanNo !== '') {
            $loan = (clone $activeLoanQuery)
                ->where('loan_no', $loanNo)
                ->first();

            if ($loan) {
                return [$loan, null];
            }

            $loanAnyStatus = KoperasiLoan::query()
                ->where('koperasi_member_id', $member->id)
                ->where('loan_no', $loanNo)
                ->first();
            if ($loanAnyStatus) {
                return [null, 'Pinjaman ditemukan tetapi statusnya bukan berjalan/macet.'];
            }

            return [null, 'No pinjaman tidak ditemukan untuk nomor rekening ini.'];
        }

        $activeLoans = (clone $activeLoanQuery)->limit(2)->get();
        if ($activeLoans->count() === 1) {
            return [$activeLoans->first(), null];
        }

        if ($activeLoans->count() > 1) {
            return [null, 'Member memiliki lebih dari satu pinjaman aktif. Isi No Pinjaman terlebih dahulu.'];
        }

        return [null, 'Member ini tidak memiliki pinjaman aktif.'];
    }

    private function getReportData(string $q): array
    {
        $userId = auth()->user()->tenantUserId();

        $members = KoperasiMember::query()
            ->where('user_id', $userId)
            ->withSum('savings as savings_total', 'amount')
            ->with([
                'loans' => function ($query) {
                    $query->withSum('installments as paid_principal', 'amount_principal')
                        ->withSum('installments as paid_interest', 'amount_interest')
                        ->withSum('installments as paid_penalty', 'amount_penalty');
                },
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('member_no', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('name')
            ->get()
            ->map(function ($member) {
                $disbursed = 0.0;
                $outstanding = 0.0;

                foreach ($member->loans as $loan) {
                    $disbursed += (float) $loan->principal_amount;
                    $outstanding += max(0, $this->loanTotalBill($loan) - $this->loanTotalPaid($loan));
                }

                $member->loan_disbursed = $disbursed;
                $member->loan_outstanding = $outstanding;

                return $member;
            });

        $loanSummary = KoperasiLoan::query()
            ->whereHas('member', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->withSum('installments as paid_principal', 'amount_principal')
            ->withSum('installments as paid_interest', 'amount_interest')
            ->withSum('installments as paid_penalty', 'amount_penalty')
            ->get();

        $summary = [
            'total_members' => (int) $members->count(),
            'total_savings' => (float) KoperasiSaving::query()->whereHas('member', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->sum('amount'),
            'total_loan_disbursed' => (float) $loanSummary->sum(fn ($loan) => (float) $loan->principal_amount),
            'total_loan_outstanding' => (float) $loanSummary->sum(
                fn ($loan) => max(0, $this->loanTotalBill($loan) - $this->loanTotalPaid($loan))
            ),
        ];

        return [$members, $summary];
    }

    private function refreshLoanSummary(KoperasiLoan $loan): void
    {
        $loan->loadSum('installments as paid_principal', 'amount_principal');
        $loan->loadSum('installments as paid_interest', 'amount_interest');
        $loan->loadSum('installments as paid_penalty', 'amount_penalty');
    }

    private function syncLoanStatus(KoperasiLoan $loan): void
    {
        $this->refreshLoanSummary($loan);

        $remaining = max(0, $this->loanTotalBill($loan) - $this->loanTotalPaid($loan));
        if ($remaining <= 0 && $loan->status !== 'lunas') {
            $loan->update(['status' => 'lunas']);
            return;
        }

        if ($remaining > 0 && $loan->status === 'lunas') {
            $loan->update(['status' => 'berjalan']);
        }
    }

    private function loanTotalBill(KoperasiLoan $loan): float
    {
        $principal = (float) $loan->principal_amount;
        $interest = $this->loanInterestBill($loan);

        return $principal + $interest;
    }

    private function loanTotalPaid(KoperasiLoan $loan): float
    {
        return (float) ($loan->paid_principal ?? 0)
            + (float) ($loan->paid_interest ?? 0);
    }

    private function loanInterestBill(KoperasiLoan $loan): float
    {
        $principal = (float) $loan->principal_amount;
        $interest = $principal * ((float) $loan->interest_percent / 100);
        $adminFee = (float) $loan->admin_fee;

        return $interest + $adminFee;
    }

    private function loanBaseInstallment(KoperasiLoan $loan): float
    {
        $tenor = max(1, (int) $loan->tenor_months);
        return round($this->loanTotalBill($loan) / $tenor, 2);
    }

    private function installmentExpectedAmount(KoperasiLoan $loan, int $installmentNo, float $paidBefore): float
    {
        $installmentNo = max(1, $installmentNo);
        $baseInstallment = $this->loanBaseInstallment($loan);
        $totalBill = $this->loanTotalBill($loan);
        $dueBefore = min($totalBill, round($baseInstallment * max(0, $installmentNo - 1), 2));
        $carryShortfall = max(0, $dueBefore - $paidBefore);
        $remaining = max(0, $totalBill - $paidBefore);

        return round(min($remaining, $baseInstallment + $carryShortfall), 2);
    }

    private function allocateInstallmentCorePayment(KoperasiLoan $loan, float $corePayment): array
    {
        $interestBill = $this->loanInterestBill($loan);
        $interestPaid = (float) ($loan->paid_interest ?? 0);
        $interestRemaining = max(0, $interestBill - $interestPaid);

        $interestPortion = min($corePayment, $interestRemaining);
        $principalPortion = max(0, $corePayment - $interestPortion);
        $principalRemaining = max(0, (float) $loan->principal_amount - (float) ($loan->paid_principal ?? 0));
        $principalPortion = min($principalPortion, $principalRemaining);

        return [
            'principal' => round($principalPortion, 2),
            'interest' => round($interestPortion, 2),
        ];
    }

    private function buildDashboardInsights(array $summary, $topRiskMembers): array
    {
        $insights = [];

        if ((int) $summary['members_with_installments'] === 0) {
            $insights[] = 'Belum ada riwayat cicilan. Prioritaskan input histori cicilan untuk memulai analisa disiplin anggota.';
            return $insights;
        }

        if ((float) $summary['avg_on_time_rate'] >= 90) {
            $insights[] = 'Kualitas pembayaran baik: rata-rata ketepatan waktu cicilan ' . $summary['avg_on_time_rate'] . '%.';
        } elseif ((float) $summary['avg_on_time_rate'] >= 75) {
            $insights[] = 'Ketepatan waktu cicilan berada di level menengah (' . $summary['avg_on_time_rate'] . '%). Perlu monitoring anggota berisiko.';
        } else {
            $insights[] = 'Ketepatan waktu cicilan rendah (' . $summary['avg_on_time_rate'] . '%). Pertimbangkan kebijakan penagihan terjadwal.';
        }

        if ((float) $summary['total_shortfall'] > 0) {
            $insights[] = 'Akumulasi kurang bayar angsuran saat ini Rp '
                . number_format((float) $summary['total_shortfall'], 0, ',', '.')
                . '. Prioritaskan penagihan ke anggota dengan shortfall terbesar.';
        } else {
            $insights[] = 'Tidak ada akumulasi kurang bayar. Kualitas kesesuaian nominal angsuran tergolong sehat.';
        }

        if ((float) $summary['net_cashflow_ytd'] >= 0) {
            $insights[] = 'Arus kas koperasi tahun berjalan positif sebesar Rp '
                . number_format((float) $summary['net_cashflow_ytd'], 0, ',', '.')
                . '.';
        } else {
            $insights[] = 'Arus kas koperasi tahun berjalan negatif sebesar Rp '
                . number_format(abs((float) $summary['net_cashflow_ytd']), 0, ',', '.')
                . '. Evaluasi pencairan pinjaman baru dan percepat collection.';
        }

        if ((float) $summary['total_loan_outstanding'] > ((float) $summary['total_savings'] * 0.9)) {
            $insights[] = 'Sisa pinjaman mendekati total simpanan. Likuiditas berpotensi ketat, pertimbangkan pembatasan plafon pinjaman baru.';
        }

        $firstRisk = $topRiskMembers->first();
        if ($firstRisk) {
            $insights[] = 'Anggota prioritas pembinaan: '
                . $firstRisk['name']
                . ' (terlambat '
                . $firstRisk['late_count']
                . ' kali, kurang bayar Rp '
                . number_format((float) $firstRisk['shortfall_total'], 0, ',', '.')
                . ').';
        }

        return $insights;
    }

    private function truncateText(string $text, int $maxLength): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (strlen($clean) <= $maxLength) {
            return $clean;
        }

        return substr($clean, 0, $maxLength - 3) . '...';
    }

    private function buildExcelXml($members, array $summary, string $q): string
    {
        $rows = [];
        $rows[] = [$this->xmlCell('Laporan Koperasi Simpan Pinjam')];
        $rows[] = [$this->xmlCell('Tanggal Export'), $this->xmlCell(date('d-m-Y H:i:s'))];
        $rows[] = [$this->xmlCell('User'), $this->xmlCell(auth()->user()->name)];
        $rows[] = [$this->xmlCell('Filter'), $this->xmlCell($q !== '' ? $q : '-')];
        $rows[] = [];
        $rows[] = [$this->xmlCell('Ringkasan')];
        $rows[] = [$this->xmlCell('Total Member'), $this->xmlCell((string) $summary['total_members'], 'Number')];
        $rows[] = [$this->xmlCell('Total Simpanan'), $this->xmlCell((string) $summary['total_savings'], 'Number')];
        $rows[] = [$this->xmlCell('Total Pinjaman Cair'), $this->xmlCell((string) $summary['total_loan_disbursed'], 'Number')];
        $rows[] = [$this->xmlCell('Total Sisa Pinjaman'), $this->xmlCell((string) $summary['total_loan_outstanding'], 'Number')];
        $rows[] = [];
        $rows[] = [
            $this->xmlCell('No'),
            $this->xmlCell('No Rekening'),
            $this->xmlCell('Nama'),
            $this->xmlCell('Tanggal Gabung'),
            $this->xmlCell('Total Simpanan'),
            $this->xmlCell('Pinjaman Cair'),
            $this->xmlCell('Sisa Pinjaman'),
            $this->xmlCell('Status'),
        ];

        foreach ($members->values() as $index => $member) {
            $rows[] = [
                $this->xmlCell((string) ($index + 1), 'Number'),
                $this->xmlCell((string) $member->member_no),
                $this->xmlCell((string) $member->name),
                $this->xmlCell(optional($member->join_date)->format('d-m-Y') ?: '-'),
                $this->xmlCell((string) ((float) ($member->savings_total ?? 0)), 'Number'),
                $this->xmlCell((string) ((float) $member->loan_disbursed), 'Number'),
                $this->xmlCell((string) ((float) $member->loan_outstanding), 'Number'),
                $this->xmlCell((string) ucfirst((string) $member->status)),
            ];
        }

        $tableRows = [];
        foreach ($rows as $row) {
            if (empty($row)) {
                $tableRows[] = '<Row><Cell><Data ss:Type="String"></Data></Cell></Row>';
                continue;
            }

            $tableRows[] = '<Row>' . implode('', $row) . '</Row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
            . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<Worksheet ss:Name="Laporan Koperasi"><Table>'
            . implode('', $tableRows)
            . '</Table></Worksheet></Workbook>';
    }

    private function xmlCell(string $value, string $type = 'String'): string
    {
        $safeValue = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return '<Cell><Data ss:Type="' . $type . '">' . $safeValue . '</Data></Cell>';
    }

    private function buildSimplePdf(array $lines): string
    {
        $linesPerPage = 48;
        $pages = array_chunk($lines, $linesPerPage);

        $objects = [];
        $addObject = function (string $content) use (&$objects): int {
            $objects[] = $content;
            return count($objects);
        };

        $fontId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $pagesId = $addObject('');

        $pageIds = [];
        foreach ($pages as $pageLines) {
            $y = 810;
            $streamLines = ['BT', '/F1 10 Tf'];
            foreach ($pageLines as $line) {
                $streamLines[] = sprintf(
                    '1 0 0 1 30 %d Tm (%s) Tj',
                    $y,
                    $this->escapePdfText($this->normalizePdfText($line))
                );
                $y -= 16;
            }
            $streamLines[] = 'ET';
            $stream = implode("\n", $streamLines) . "\n";

            $contentId = $addObject("<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream");
            $pageId = $addObject(
                "<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 595 842] "
                . "/Resources << /Font << /F1 {$fontId} 0 R >> >> "
                . "/Contents {$contentId} 0 R >>"
            );
            $pageIds[] = $pageId;
        }

        $kids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageIds));
        $objects[$pagesId - 1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageIds) . " >>";

        $catalogId = $addObject("<< /Type /Catalog /Pages {$pagesId} 0 R >>");

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $count = count($objects);

        for ($i = 1; $i <= $count; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i - 1] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($count + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . ($count + 1) . " /Root {$catalogId} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function normalizePdfText(string $text): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $clean);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $clean;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $text
        );
    }
}
