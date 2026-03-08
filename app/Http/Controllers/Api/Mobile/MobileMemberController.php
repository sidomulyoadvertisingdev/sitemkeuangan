<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\KoperasiSaving;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileMemberController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = $request->user()->tenantUserId();
        $summary = $request->user()->isCooperativeMode()
            ? $this->buildCooperativeSummary((int) $tenantId)
            : $this->buildOrganizationSummary((int) $tenantId);

        return response()->json($summary);
    }

    public function transactions(Request $request): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = $request->user()->tenantUserId();
        $items = $request->user()->isCooperativeMode()
            ? $this->cooperativeTransactionsQuery((int) $tenantId)->limit(200)->get()->map(fn ($row) => $this->transformCooperativeTransaction($row))->values()
            : $this->organizationTransactionsQuery((int) $tenantId)->limit(200)->get()->map(fn ($row) => $this->transformOrganizationTransaction($row))->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $items->count(),
            ],
        ]);
    }

    public function showTransaction(Request $request, int $transactionId): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = $request->user()->tenantUserId();
        $item = $request->user()->isCooperativeMode()
            ? $this->cooperativeTransactionsQuery((int) $tenantId)->where('koperasi_savings.id', $transactionId)->first()
            : $this->organizationTransactionsQuery((int) $tenantId)->where('transactions.id', $transactionId)->first();

        if (!$item) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        return response()->json(
            $request->user()->isCooperativeMode()
                ? $this->transformCooperativeTransaction($item)
                : $this->transformOrganizationTransaction($item)
        );
    }

    private function ensureMemberAccess(?User $user): ?JsonResponse
    {
        if (!$user) {
            return response()->json([
                'message' => 'User tidak terautentikasi.',
            ], 401);
        }

        if (!$user->isApproved()) {
            return response()->json([
                'message' => 'Akun belum aktif untuk mengakses aplikasi mobile.',
            ], 403);
        }

        return null;
    }

    private function buildOrganizationSummary(int $tenantId): array
    {
        $query = Transaction::query()->where('user_id', $tenantId);
        $totalSavings = (float) (clone $query)->where('type', 'income')->sum('amount');
        $totalWithdrawals = (float) (clone $query)->where('type', 'expense')->sum('amount');
        $lastTransactionAt = (clone $query)->max('date');

        return [
            'savings_balance' => $totalSavings - $totalWithdrawals,
            'total_savings' => $totalSavings,
            'total_withdrawals' => $totalWithdrawals,
            'last_transaction_at' => $lastTransactionAt,
        ];
    }

    private function buildCooperativeSummary(int $tenantId): array
    {
        $query = KoperasiSaving::query()
            ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_savings.koperasi_member_id')
            ->where('koperasi_members.user_id', $tenantId);

        $totalSavings = (float) (clone $query)->where('koperasi_savings.amount', '>', 0)->sum('koperasi_savings.amount');
        $withdrawalsRaw = (float) (clone $query)->where('koperasi_savings.amount', '<', 0)->sum('koperasi_savings.amount');
        $balance = (float) (clone $query)->sum('koperasi_savings.amount');
        $lastTransactionAt = (clone $query)->max('koperasi_savings.transaction_date');

        return [
            'savings_balance' => $balance,
            'total_savings' => $totalSavings,
            'total_withdrawals' => abs($withdrawalsRaw),
            'last_transaction_at' => $lastTransactionAt,
        ];
    }

    private function organizationTransactionsQuery(int $tenantId)
    {
        return Transaction::query()
            ->where('user_id', $tenantId)
            ->select('transactions.id', 'transactions.type', 'transactions.amount', 'transactions.date', 'transactions.note')
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id');
    }

    private function cooperativeTransactionsQuery(int $tenantId)
    {
        return KoperasiSaving::query()
            ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_savings.koperasi_member_id')
            ->where('koperasi_members.user_id', $tenantId)
            ->select(
                'koperasi_savings.id',
                'koperasi_savings.type',
                'koperasi_savings.amount',
                'koperasi_savings.transaction_date as date',
                'koperasi_savings.note',
                'koperasi_members.name as member_name'
            )
            ->orderByDesc('koperasi_savings.transaction_date')
            ->orderByDesc('koperasi_savings.id');
    }

    private function transformOrganizationTransaction($row): array
    {
        return [
            'id' => (int) $row->id,
            'type' => $row->type === 'expense' ? 'penarikan' : 'setoran',
            'amount' => (float) $row->amount,
            'date' => (string) $row->date,
            'description' => $row->note,
            'note' => $row->note,
        ];
    }

    private function transformCooperativeTransaction($row): array
    {
        $type = $row->type ?: ((float) $row->amount < 0 ? 'penarikan' : 'setoran');

        return [
            'id' => (int) $row->id,
            'type' => (string) $type,
            'amount' => abs((float) $row->amount),
            'date' => (string) $row->date,
            'description' => $row->member_name ?? null,
            'note' => $row->note,
        ];
    }
}
