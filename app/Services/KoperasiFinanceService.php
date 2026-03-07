<?php

namespace App\Services;

use App\Models\KoperasiLoan;
use App\Models\KoperasiLoanInstallment;
use App\Models\KoperasiMember;
use App\Models\KoperasiSaving;
use App\Models\KoperasiWalletAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KoperasiFinanceService
{
    public function __construct(
        private readonly KoperasiWalletService $walletService
    ) {
    }

    public function build(int $userId, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $members = KoperasiMember::query()
            ->where('user_id', $userId)
            ->get(['id', 'member_no', 'name', 'status']);

        $wallets = $this->walletService->allWallets($userId);
        $walletTypeLabels = KoperasiWalletAccount::typeOptions();
        $capitalFunding = (float) $wallets->sum('opening_balance');

        $savings = KoperasiSaving::query()
            ->with(['member:id,member_no,name', 'walletAccount:id,name,wallet_type'])
            ->whereHas('member', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $loans = KoperasiLoan::query()
            ->with(['member:id,member_no,name', 'walletAccount:id,name,wallet_type'])
            ->withSum('installments as paid_principal', 'amount_principal')
            ->withSum('installments as paid_interest', 'amount_interest')
            ->withSum('installments as paid_penalty', 'amount_penalty')
            ->whereHas('member', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('disbursed_at')
            ->orderBy('id')
            ->get();

        $installments = KoperasiLoanInstallment::query()
            ->with([
                'principalWalletAccount:id,name,wallet_type',
                'incomeWalletAccount:id,name,wallet_type',
                'loan:id,koperasi_member_id,loan_no,principal_amount,interest_percent,admin_fee,disbursed_at,status',
                'loan.member:id,member_no,name',
            ])
            ->whereHas('loan.member', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $periodSavings = $savings->filter(fn (KoperasiSaving $saving) => $this->isWithinDateRange($saving->transaction_date, $start, $end))->values();
        $periodLoans = $loans->filter(fn (KoperasiLoan $loan) => $this->isWithinDateRange($loan->disbursed_at, $start, $end))->values();
        $periodInstallments = $installments->filter(fn (KoperasiLoanInstallment $installment) => $this->isWithinDateRange($installment->paid_at, $start, $end))->values();

        $positiveSavingsAll = (float) $savings->where('amount', '>', 0)->sum('amount');
        $withdrawalsAll = (float) abs($savings->where('amount', '<', 0)->sum('amount'));
        $principalDisbursedAll = (float) $loans->sum('principal_amount');
        $serviceBilledAll = (float) $loans->sum(fn (KoperasiLoan $loan) => $this->loanServiceBill($loan));
        $principalCollectedAll = (float) $installments->sum('amount_principal');
        $interestCollectedAll = (float) $installments->sum('amount_interest');
        $penaltyCollectedAll = (float) $installments->sum('amount_penalty');
        $installmentsCollectedAll = $principalCollectedAll + $interestCollectedAll + $penaltyCollectedAll;

        $positiveSavingsPeriod = (float) $periodSavings->where('amount', '>', 0)->sum('amount');
        $withdrawalsPeriod = (float) abs($periodSavings->where('amount', '<', 0)->sum('amount'));
        $principalDisbursedPeriod = (float) $periodLoans->sum('principal_amount');
        $serviceBilledPeriod = (float) $periodLoans->sum(fn (KoperasiLoan $loan) => $this->loanServiceBill($loan));
        $adminBilledPeriod = (float) $periodLoans->sum('admin_fee');
        $principalCollectedPeriod = (float) $periodInstallments->sum('amount_principal');
        $interestCollectedPeriod = (float) $periodInstallments->sum('amount_interest');
        $penaltyCollectedPeriod = (float) $periodInstallments->sum('amount_penalty');
        $installmentsCollectedPeriod = $principalCollectedPeriod + $interestCollectedPeriod + $penaltyCollectedPeriod;

        $cashInPeriod = $positiveSavingsPeriod + $installmentsCollectedPeriod;
        $cashOutPeriod = $withdrawalsPeriod + $principalDisbursedPeriod;
        $netCashflowPeriod = $cashInPeriod - $cashOutPeriod;

        $walletBalances = $this->buildWalletBalances($wallets, $savings, $loans, $installments, $walletTypeLabels);
        $cashPosition = (float) $walletBalances->sum('closing_balance');
        $savingsLiability = (float) $savings->sum('amount');
        $outstandingPrincipal = max(0, $principalDisbursedAll - $principalCollectedAll);
        $outstandingService = max(0, $serviceBilledAll - $interestCollectedAll);
        $loanReceivable = $outstandingPrincipal + $outstandingService;
        $shortfallOutstanding = (float) $installments->sum('shortfall_amount');
        $accruedResult = $serviceBilledAll + $penaltyCollectedAll;

        $cashflowBreakdown = collect([
            [
                'label' => 'Setoran Simpanan',
                'direction' => 'in',
                'amount' => $positiveSavingsPeriod,
                'description' => 'Kas masuk dari simpanan pokok, wajib, dan sukarela.',
            ],
            [
                'label' => 'Penerimaan Pokok Angsuran',
                'direction' => 'in',
                'amount' => $principalCollectedPeriod,
                'description' => 'Pelunasan pokok pinjaman dari anggota.',
            ],
            [
                'label' => 'Penerimaan Jasa/Admin Pinjaman',
                'direction' => 'in',
                'amount' => $interestCollectedPeriod,
                'description' => 'Kas masuk dari bunga dan administrasi yang dibayar lewat angsuran.',
            ],
            [
                'label' => 'Penerimaan Denda',
                'direction' => 'in',
                'amount' => $penaltyCollectedPeriod,
                'description' => 'Kas masuk dari denda keterlambatan atau kurang bayar.',
            ],
            [
                'label' => 'Withdraw Simpanan',
                'direction' => 'out',
                'amount' => $withdrawalsPeriod,
                'description' => 'Kas keluar untuk penarikan simpanan anggota.',
            ],
            [
                'label' => 'Pencairan Pinjaman',
                'direction' => 'out',
                'amount' => $principalDisbursedPeriod,
                'description' => 'Kas keluar untuk pinjaman yang dicairkan ke anggota.',
            ],
        ])->filter(fn (array $row) => $row['amount'] > 0)->values();

        $savingsByType = $periodSavings
            ->groupBy('type')
            ->map(function (Collection $rows, string $type) use ($positiveSavingsPeriod, $withdrawalsPeriod) {
                $netAmount = (float) $rows->sum('amount');
                $baseAmount = $netAmount >= 0 ? max($positiveSavingsPeriod, 1) : max($withdrawalsPeriod, 1);

                return [
                    'type' => $type,
                    'label' => 'Simpanan ' . ucfirst($type),
                    'net_amount' => $netAmount,
                    'percentage' => round((abs($netAmount) / $baseAmount) * 100, 2),
                ];
            })
            ->sortByDesc(fn (array $row) => abs($row['net_amount']))
            ->values();

        $loanStatusSummary = $loans
            ->groupBy('status')
            ->map(function (Collection $rows, string $status) {
                $outstanding = (float) $rows->sum(function (KoperasiLoan $loan) {
                    return max(0, $this->loanTotalBill($loan) - $this->loanTotalPaid($loan));
                });

                return [
                    'status' => $status,
                    'label' => ucfirst($status),
                    'count' => (int) $rows->count(),
                    'principal_amount' => (float) $rows->sum('principal_amount'),
                    'outstanding_amount' => $outstanding,
                ];
            })
            ->sortByDesc('outstanding_amount')
            ->values();

        $walletBalancesByType = $walletBalances
            ->groupBy('wallet_type_label')
            ->map(function (Collection $rows, string $typeLabel) {
                return [
                    'type_label' => $typeLabel,
                    'total_opening_balance' => (float) $rows->sum('opening_balance'),
                    'total_inflow' => (float) $rows->sum('inflow'),
                    'total_outflow' => (float) $rows->sum('outflow'),
                    'total_closing_balance' => (float) $rows->sum('closing_balance'),
                    'wallets' => $rows->values(),
                ];
            })
            ->values();

        $activityRows = $this->buildActivityRows($periodSavings, $periodLoans, $periodInstallments);
        $journalEntries = $this->buildJournalEntries($periodSavings, $periodLoans, $periodInstallments);
        $trialBalance = $this->buildTrialBalance(
            $cashPosition,
            $outstandingPrincipal,
            $outstandingService,
            $savingsLiability,
            $serviceBilledAll,
            $penaltyCollectedAll,
            $capitalFunding
        );

        $trialTotals = [
            'debit' => (float) $trialBalance->sum('debit'),
            'credit' => (float) $trialBalance->sum('credit'),
        ];
        $trialTotals['difference'] = round($trialTotals['debit'] - $trialTotals['credit'], 2);

        $statement = [
            'assets' => collect([
                ['label' => 'Kas Koperasi', 'amount' => $cashPosition],
                ['label' => 'Piutang Pinjaman Pokok', 'amount' => $outstandingPrincipal],
                ['label' => 'Piutang Jasa/Admin Pinjaman', 'amount' => $outstandingService],
            ]),
            'liabilities' => collect([
                ['label' => 'Simpanan Anggota', 'amount' => $savingsLiability],
            ]),
            'equity' => collect([
                ['label' => 'Modal Koperasi', 'amount' => $capitalFunding],
                ['label' => 'Akumulasi Pendapatan Jasa Pinjaman', 'amount' => $serviceBilledAll],
                ['label' => 'Akumulasi Pendapatan Denda', 'amount' => $penaltyCollectedAll],
            ]),
        ];

        $statement['total_assets'] = (float) $statement['assets']->sum('amount');
        $statement['total_liabilities'] = (float) $statement['liabilities']->sum('amount');
        $statement['total_equity'] = (float) $statement['equity']->sum('amount');
        $statement['total_liabilities_equity'] = $statement['total_liabilities'] + $statement['total_equity'];

        return [
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'summary' => [
                'members' => (int) $members->count(),
                'active_members' => (int) $members->where('status', 'aktif')->count(),
                'cash_in_period' => $cashInPeriod,
                'cash_out_period' => $cashOutPeriod,
                'net_cashflow_period' => $netCashflowPeriod,
                'cash_position' => $cashPosition,
                'capital_balance' => $capitalFunding,
                'savings_liability' => $savingsLiability,
                'loan_receivable' => $loanReceivable,
                'outstanding_principal' => $outstandingPrincipal,
                'outstanding_service' => $outstandingService,
                'service_billed_period' => $serviceBilledPeriod,
                'service_billed_total' => $serviceBilledAll,
                'admin_billed_period' => $adminBilledPeriod,
                'principal_disbursed_period' => $principalDisbursedPeriod,
                'principal_collected_period' => $principalCollectedPeriod,
                'interest_collected_period' => $interestCollectedPeriod,
                'penalty_collected_period' => $penaltyCollectedPeriod,
                'installments_collected_period' => $installmentsCollectedPeriod,
                'shortfall_outstanding' => $shortfallOutstanding,
                'accrued_result' => $accruedResult,
                'wallet_count' => (int) $walletBalances->count(),
            ],
            'cashflowBreakdown' => $cashflowBreakdown,
            'savingsByType' => $savingsByType,
            'loanStatusSummary' => $loanStatusSummary,
            'walletBalances' => $walletBalances,
            'walletBalancesByType' => $walletBalancesByType,
            'activityRows' => $activityRows,
            'chartOfAccounts' => collect([
                ['code' => '111', 'name' => 'Kas Koperasi', 'group' => 'Aset', 'normal_balance' => 'Debit', 'description' => 'Kas aktual dari seluruh dompet koperasi yang aktif.'],
                ['code' => '112', 'name' => 'Piutang Pinjaman Pokok', 'group' => 'Aset', 'normal_balance' => 'Debit', 'description' => 'Sisa pokok pinjaman yang belum dibayar anggota.'],
                ['code' => '113', 'name' => 'Piutang Jasa/Admin Pinjaman', 'group' => 'Aset', 'normal_balance' => 'Debit', 'description' => 'Sisa jasa pinjaman dan admin yang sudah dibukukan namun belum tertagih.'],
                ['code' => '211', 'name' => 'Simpanan Anggota', 'group' => 'Liabilitas', 'normal_balance' => 'Kredit', 'description' => 'Kewajiban koperasi atas saldo simpanan anggota.'],
                ['code' => '311', 'name' => 'Modal Koperasi', 'group' => 'Ekuitas', 'normal_balance' => 'Kredit', 'description' => 'Pendanaan awal/penyetoran modal yang membentuk saldo awal dompet.'],
                ['code' => '411', 'name' => 'Pendapatan Jasa Pinjaman', 'group' => 'Pendapatan', 'normal_balance' => 'Kredit', 'description' => 'Pendapatan bunga dan admin pinjaman berbasis accrual manajemen.'],
                ['code' => '412', 'name' => 'Pendapatan Denda', 'group' => 'Pendapatan', 'normal_balance' => 'Kredit', 'description' => 'Pendapatan denda yang sudah diterima dari angsuran.'],
            ]),
            'journalEntries' => $journalEntries,
            'trialBalance' => $trialBalance,
            'trialBalanceTotals' => $trialTotals,
            'statement' => $statement,
            'notes' => [
                'Saldo kas sekarang dibaca dari saldo masing-masing dompet accounting koperasi.',
                'Dompet penampungan, modal, pinjaman, dan pendapatan dapat dipisahkan agar arus dana lebih jelas.',
                'Pendapatan jasa/admin tetap ditampilkan dengan basis accrual manajemen agar posisi piutang dan hasil usaha lebih informatif.',
            ],
        ];
    }

    private function buildWalletBalances(
        Collection $wallets,
        Collection $savings,
        Collection $loans,
        Collection $installments,
        array $walletTypeLabels
    ): Collection {
        return $wallets->map(function (KoperasiWalletAccount $wallet) use ($savings, $loans, $installments, $walletTypeLabels) {
            $savingInflow = (float) $savings
                ->where('wallet_account_id', $wallet->id)
                ->where('amount', '>', 0)
                ->sum('amount');

            $savingOutflow = (float) abs($savings
                ->where('wallet_account_id', $wallet->id)
                ->where('amount', '<', 0)
                ->sum('amount'));

            $loanOutflow = (float) $loans
                ->where('wallet_account_id', $wallet->id)
                ->sum('principal_amount');

            $principalInstallmentInflow = (float) $installments
                ->where('principal_wallet_account_id', $wallet->id)
                ->sum('amount_principal');

            $incomeInstallmentInflow = (float) $installments
                ->where('income_wallet_account_id', $wallet->id)
                ->sum(function (KoperasiLoanInstallment $installment) {
                    return (float) $installment->amount_interest + (float) $installment->amount_penalty;
                });

            $inflow = $savingInflow + $principalInstallmentInflow + $incomeInstallmentInflow;
            $outflow = $savingOutflow + $loanOutflow;
            $openingBalance = (float) $wallet->opening_balance;

            return [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'wallet_type' => $wallet->wallet_type,
                'wallet_type_label' => $walletTypeLabels[$wallet->wallet_type] ?? ucfirst($wallet->wallet_type),
                'opening_balance' => $openingBalance,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'closing_balance' => $openingBalance + $inflow - $outflow,
                'is_active' => (bool) $wallet->is_active,
                'description' => (string) ($wallet->description ?? ''),
            ];
        })->values();
    }

    private function buildActivityRows(
        Collection $savings,
        Collection $loans,
        Collection $installments
    ): Collection {
        $rows = collect();

        foreach ($savings as $saving) {
            $isWithdrawal = (float) $saving->amount < 0;
            $rows->push([
                'sort_at' => Carbon::parse($saving->transaction_date)->format('Y-m-d H:i:s'),
                'date' => Carbon::parse($saving->transaction_date)->format('d-m-Y'),
                'reference' => ($isWithdrawal ? 'WD-' : 'SV-') . str_pad((string) $saving->id, 8, '0', STR_PAD_LEFT),
                'member_name' => (string) ($saving->member?->name ?? '-'),
                'member_no' => (string) ($saving->member?->member_no ?? '-'),
                'activity' => $isWithdrawal ? 'Withdraw Simpanan' : 'Setoran Simpanan ' . ucfirst((string) $saving->type),
                'cash_direction' => $isWithdrawal ? 'Kas Keluar' : 'Kas Masuk',
                'wallet_name' => (string) ($saving->walletAccount?->name ?? 'Belum Dipetakan'),
                'amount' => abs((float) $saving->amount),
                'note' => (string) ($saving->note ?? '-'),
            ]);
        }

        foreach ($loans as $loan) {
            $rows->push([
                'sort_at' => Carbon::parse($loan->disbursed_at)->format('Y-m-d H:i:s'),
                'date' => Carbon::parse($loan->disbursed_at)->format('d-m-Y'),
                'reference' => (string) $loan->loan_no,
                'member_name' => (string) ($loan->member?->name ?? '-'),
                'member_no' => (string) ($loan->member?->member_no ?? '-'),
                'activity' => 'Pencairan Pinjaman',
                'cash_direction' => 'Kas Keluar',
                'wallet_name' => (string) ($loan->walletAccount?->name ?? 'Belum Dipetakan'),
                'amount' => (float) $loan->principal_amount,
                'note' => trim('Pokok Rp ' . number_format((float) $loan->principal_amount, 0, ',', '.')
                    . ' | Jasa/Admin dibukukan Rp ' . number_format($this->loanServiceBill($loan), 0, ',', '.')
                    . ($loan->note ? ' | ' . $loan->note : '')),
            ]);
        }

        foreach ($installments as $installment) {
            $rows->push([
                'sort_at' => Carbon::parse($installment->paid_at)->format('Y-m-d H:i:s'),
                'date' => Carbon::parse($installment->paid_at)->format('d-m-Y'),
                'reference' => 'ANG-' . str_pad((string) $installment->id, 8, '0', STR_PAD_LEFT),
                'member_name' => (string) ($installment->loan?->member?->name ?? '-'),
                'member_no' => (string) ($installment->loan?->member?->member_no ?? '-'),
                'activity' => 'Penerimaan Angsuran',
                'cash_direction' => 'Kas Masuk',
                'wallet_name' => trim(
                    'Pokok: ' . (string) ($installment->principalWalletAccount?->name ?? 'Belum Dipetakan')
                    . ' | Pendapatan: ' . (string) ($installment->incomeWalletAccount?->name ?? 'Belum Dipetakan')
                ),
                'amount' => (float) $installment->amount_principal + (float) $installment->amount_interest + (float) $installment->amount_penalty,
                'note' => trim(
                    'No Pinjaman ' . (string) ($installment->loan?->loan_no ?? '-')
                    . ' | Pokok Rp ' . number_format((float) $installment->amount_principal, 0, ',', '.')
                    . ' | Jasa Rp ' . number_format((float) $installment->amount_interest, 0, ',', '.')
                    . ' | Denda Rp ' . number_format((float) $installment->amount_penalty, 0, ',', '.')
                    . ($installment->note ? ' | ' . $installment->note : '')
                ),
            ]);
        }

        return $rows
            ->sortByDesc('sort_at')
            ->values();
    }

    private function buildJournalEntries(
        Collection $savings,
        Collection $loans,
        Collection $installments
    ): Collection {
        $entries = collect();

        foreach ($savings as $saving) {
            $amount = abs((float) $saving->amount);
            $isWithdrawal = (float) $saving->amount < 0;
            $walletLabel = (string) ($saving->walletAccount?->name ?? 'Belum Dipetakan');

            $entries->push([
                'sort_at' => Carbon::parse($saving->transaction_date)->format('Y-m-d H:i:s'),
                'date' => Carbon::parse($saving->transaction_date)->format('d-m-Y'),
                'reference' => ($isWithdrawal ? 'JU-WD-' : 'JU-SV-') . str_pad((string) $saving->id, 8, '0', STR_PAD_LEFT),
                'description' => ($isWithdrawal
                    ? 'Withdraw simpanan anggota '
                    : 'Setoran simpanan anggota ')
                    . (string) ($saving->member?->name ?? '-')
                    . ' via dompet '
                    . $walletLabel,
                'lines' => $isWithdrawal
                    ? [
                        $this->journalLine('211', 'Simpanan Anggota', $amount, 0.0),
                        $this->journalLine('111', 'Kas Koperasi - ' . $walletLabel, 0.0, $amount),
                    ]
                    : [
                        $this->journalLine('111', 'Kas Koperasi - ' . $walletLabel, $amount, 0.0),
                        $this->journalLine('211', 'Simpanan Anggota', 0.0, $amount),
                    ],
                'total' => $amount,
            ]);
        }

        foreach ($loans as $loan) {
            $principal = (float) $loan->principal_amount;
            $service = $this->loanServiceBill($loan);
            $walletLabel = (string) ($loan->walletAccount?->name ?? 'Belum Dipetakan');

            $entries->push([
                'sort_at' => Carbon::parse($loan->disbursed_at)->format('Y-m-d H:i:s'),
                'date' => Carbon::parse($loan->disbursed_at)->format('d-m-Y'),
                'reference' => 'JU-' . (string) $loan->loan_no,
                'description' => 'Pencairan pinjaman ' . (string) $loan->loan_no . ' untuk ' . (string) ($loan->member?->name ?? '-') . ' dari dompet ' . $walletLabel,
                'lines' => [
                    $this->journalLine('112', 'Piutang Pinjaman Pokok', $principal, 0.0),
                    $this->journalLine('113', 'Piutang Jasa/Admin Pinjaman', $service, 0.0),
                    $this->journalLine('111', 'Kas Koperasi - ' . $walletLabel, 0.0, $principal),
                    $this->journalLine('411', 'Pendapatan Jasa Pinjaman', 0.0, $service),
                ],
                'total' => $principal + $service,
            ]);
        }

        foreach ($installments as $installment) {
            $principal = (float) $installment->amount_principal;
            $interest = (float) $installment->amount_interest;
            $penalty = (float) $installment->amount_penalty;
            $total = $principal + $interest + $penalty;
            $principalWalletLabel = (string) ($installment->principalWalletAccount?->name ?? 'Belum Dipetakan');
            $incomeWalletLabel = (string) ($installment->incomeWalletAccount?->name ?? 'Belum Dipetakan');

            $lines = [];
            if ($principal > 0) {
                $lines[] = $this->journalLine('111', 'Kas Koperasi - ' . $principalWalletLabel, $principal, 0.0);
                $lines[] = $this->journalLine('112', 'Piutang Pinjaman Pokok', 0.0, $principal);
            }

            if (($interest + $penalty) > 0) {
                $lines[] = $this->journalLine('111', 'Kas Koperasi - ' . $incomeWalletLabel, $interest + $penalty, 0.0);
            }

            if ($interest > 0) {
                $lines[] = $this->journalLine('113', 'Piutang Jasa/Admin Pinjaman', 0.0, $interest);
            }

            if ($penalty > 0) {
                $lines[] = $this->journalLine('412', 'Pendapatan Denda', 0.0, $penalty);
            }

            $entries->push([
                'sort_at' => Carbon::parse($installment->paid_at)->format('Y-m-d H:i:s'),
                'date' => Carbon::parse($installment->paid_at)->format('d-m-Y'),
                'reference' => 'JU-ANG-' . str_pad((string) $installment->id, 8, '0', STR_PAD_LEFT),
                'description' => 'Penerimaan angsuran pinjaman ' . (string) ($installment->loan?->loan_no ?? '-')
                    . ' | dompet pokok: ' . $principalWalletLabel
                    . ' | dompet pendapatan: ' . $incomeWalletLabel,
                'lines' => $lines,
                'total' => $total,
            ]);
        }

        return $entries
            ->sortByDesc('sort_at')
            ->values();
    }

    private function buildTrialBalance(
        float $cashPosition,
        float $outstandingPrincipal,
        float $outstandingService,
        float $savingsLiability,
        float $serviceBilled,
        float $penaltyCollected,
        float $capitalFunding
    ): Collection {
        return collect([
            $this->trialBalanceLine('111', 'Kas Koperasi', $cashPosition, 'debit'),
            $this->trialBalanceLine('112', 'Piutang Pinjaman Pokok', $outstandingPrincipal, 'debit'),
            $this->trialBalanceLine('113', 'Piutang Jasa/Admin Pinjaman', $outstandingService, 'debit'),
            $this->trialBalanceLine('211', 'Simpanan Anggota', $savingsLiability, 'credit'),
            $this->trialBalanceLine('311', 'Modal Koperasi', $capitalFunding, 'credit'),
            $this->trialBalanceLine('411', 'Pendapatan Jasa Pinjaman', $serviceBilled, 'credit'),
            $this->trialBalanceLine('412', 'Pendapatan Denda', $penaltyCollected, 'credit'),
        ])->values();
    }

    private function trialBalanceLine(string $code, string $name, float $amount, string $normalSide): array
    {
        $amount = round($amount, 2);

        if ($amount < 0) {
            return [
                'code' => $code,
                'name' => $name,
                'debit' => $normalSide === 'credit' ? abs($amount) : 0.0,
                'credit' => $normalSide === 'debit' ? abs($amount) : 0.0,
            ];
        }

        return [
            'code' => $code,
            'name' => $name,
            'debit' => $normalSide === 'debit' ? $amount : 0.0,
            'credit' => $normalSide === 'credit' ? $amount : 0.0,
        ];
    }

    private function journalLine(string $code, string $account, float $debit, float $credit): array
    {
        return [
            'code' => $code,
            'account' => $account,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ];
    }

    private function loanServiceBill(KoperasiLoan $loan): float
    {
        return round(
            ((float) $loan->principal_amount * ((float) $loan->interest_percent / 100))
            + (float) $loan->admin_fee,
            2
        );
    }

    private function loanTotalBill(KoperasiLoan $loan): float
    {
        return round((float) $loan->principal_amount + $this->loanServiceBill($loan), 2);
    }

    private function loanTotalPaid(KoperasiLoan $loan): float
    {
        return round((float) ($loan->paid_principal ?? 0) + (float) ($loan->paid_interest ?? 0), 2);
    }

    private function isWithinDateRange($date, Carbon $start, Carbon $end): bool
    {
        if (!$date) {
            return false;
        }

        return Carbon::parse($date)->betweenIncluded($start, $end);
    }
}
