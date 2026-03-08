<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\KoperasiLoan;
use App\Models\KoperasiMember;
use App\Models\KoperasiSaving;
use App\Models\User;
use App\Services\KoperasiWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileKoperasiController extends Controller
{
    public function __construct(
        private readonly KoperasiWalletService $walletService
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $accessError = $this->ensureKoperasiAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();

        $totalSavings = (float) KoperasiSaving::query()
            ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_savings.koperasi_member_id')
            ->where('koperasi_members.user_id', $tenantId)
            ->sum('koperasi_savings.amount');

        $totalMembers = (int) KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->count();

        $loans = KoperasiLoan::query()
            ->whereHas('member', function ($query) use ($tenantId) {
                $query->where('user_id', $tenantId);
            })
            ->with('installments')
            ->get();

        $totalLoanOutstanding = (float) $loans->sum(function (KoperasiLoan $loan) {
            $loanTotal = (float) $loan->principal_amount
                + ((float) $loan->principal_amount * ((float) $loan->interest_percent / 100))
                + (float) $loan->admin_fee;

            $paidTotal = (float) $loan->installments->sum(function ($installment) {
                return (float) $installment->amount_principal
                    + (float) $installment->amount_interest
                    + (float) $installment->amount_penalty;
            });

            return max(0, $loanTotal - $paidTotal);
        });

        return response()->json([
            'total_savings' => $totalSavings,
            'total_members' => $totalMembers,
            'total_loan_outstanding' => $totalLoanOutstanding,
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $accessError = $this->ensureKoperasiAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $q = trim((string) $request->query('q', ''));

        $members = KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->withSum('savings as savings_total', 'amount')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('member_no', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (KoperasiMember $member) => $this->transformMember($member))
            ->values();

        return response()->json([
            'data' => $members,
            'meta' => [
                'total' => $members->count(),
                'query' => $q,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $accessError = $this->ensureKoperasiAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $q = trim((string) $request->query('q', ''));

        $items = KoperasiSaving::query()
            ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_savings.koperasi_member_id')
            ->where('koperasi_members.user_id', $tenantId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('koperasi_members.name', 'like', '%' . $q . '%')
                        ->orWhere('koperasi_members.member_no', 'like', '%' . $q . '%')
                        ->orWhere('koperasi_savings.note', 'like', '%' . $q . '%');
                });
            })
            ->select(
                'koperasi_savings.id',
                'koperasi_savings.type',
                'koperasi_savings.amount',
                'koperasi_savings.transaction_date',
                'koperasi_savings.note',
                'koperasi_members.name as member_name'
            )
            ->orderByDesc('koperasi_savings.transaction_date')
            ->orderByDesc('koperasi_savings.id')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $type = $row->type ?: ((float) $row->amount < 0 ? 'penarikan' : 'setoran');

                return [
                    'id' => (int) $row->id,
                    'amount' => abs((float) $row->amount),
                    'transaction_date' => (string) $row->transaction_date,
                    'type' => (string) $type,
                    'member_name' => (string) $row->member_name,
                    'note' => $row->note,
                ];
            })
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $items->count(),
                'query' => $q,
            ],
        ]);
    }

    public function storeTransaction(Request $request, KoperasiMember $member): JsonResponse
    {
        $accessError = $this->ensureKoperasiAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if ((int) $member->user_id !== $tenantId) {
            return response()->json([
                'message' => 'Member koperasi tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:setoran,penarikan'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $walletMap = $this->walletService->defaultWalletMap($tenantId);
        $walletId = $validated['type'] === 'penarikan'
            ? ($walletMap['withdraw'] ?? null)
            : ($walletMap['saving'] ?? null);

        $wallet = $this->walletService->resolveOwnedWallet($walletId ? (int) $walletId : null, $tenantId);
        if (!$wallet) {
            $wallet = $this->walletService->activeWallets($tenantId)->first();
        }

        if (!$wallet) {
            return response()->json([
                'message' => 'Dompet koperasi tidak ditemukan.',
            ], 422);
        }

        $amount = (float) $validated['amount'];
        $memberBalance = (float) $member->savings()->sum('amount');
        if ($validated['type'] === 'penarikan' && $amount > $memberBalance) {
            return response()->json([
                'message' => 'Saldo simpanan anggota tidak mencukupi untuk penarikan.',
            ], 422);
        }

        $record = KoperasiSaving::create([
            'koperasi_member_id' => $member->id,
            'wallet_account_id' => $wallet->id,
            'type' => $validated['type'],
            'amount' => $validated['type'] === 'penarikan' ? -$amount : $amount,
            'transaction_date' => $validated['transaction_date'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Transaksi koperasi berhasil disimpan.',
            'transaction' => [
                'id' => (int) $record->id,
                'amount' => abs((float) $record->amount),
                'transaction_date' => (string) $record->transaction_date,
                'type' => (string) $record->type,
                'member_name' => (string) $member->name,
                'note' => $record->note,
            ],
        ], 201);
    }

    private function ensureKoperasiAccess(?User $user): ?JsonResponse
    {
        if (!$user) {
            return response()->json([
                'message' => 'User tidak terautentikasi.',
            ], 401);
        }

        if (!$user->isCooperativeMode()) {
            return response()->json([
                'message' => 'Fitur mobile koperasi hanya untuk mode Cooperative Finance.',
            ], 403);
        }

        if (!$user->hasPermission('koperasi.manage')) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke fitur koperasi.',
            ], 403);
        }

        return null;
    }

    private function transformMember(KoperasiMember $member): array
    {
        return [
            'id' => (int) $member->id,
            'member_no' => (string) $member->member_no,
            'name' => (string) $member->name,
            'status' => (string) $member->status,
            'savings_total' => (float) ($member->savings_total ?? 0),
        ];
    }
}
