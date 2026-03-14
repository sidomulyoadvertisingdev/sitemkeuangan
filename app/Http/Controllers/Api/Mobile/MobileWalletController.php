<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\KoperasiMember;
use App\Models\KoperasiSaving;
use App\Models\MobileTransferRequest;
use App\Services\KoperasiWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileWalletController extends Controller
{
    public function __construct(private readonly KoperasiWalletService $walletService)
    {
    }

    public function lookupAccount(Request $request): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request);
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $account = trim((string) $request->query('account', ''));

        if ($account === '') {
            return response()->json(['message' => 'Nomor rekening wajib diisi.'], 422);
        }

        $member = KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->where('member_no', $account)
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Rekening tidak ditemukan.'], 404);
        }

        return response()->json([
            'member_no' => $member->member_no,
            'name' => $member->name,
        ]);
    }

    public function topup(Request $request): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request);
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $member = $this->resolveSelfMember($tenantId);
        if (!$member) {
            return response()->json(['message' => 'Member koperasi tidak ditemukan.'], 404);
        }

        $adminAccount = \App\Models\BankAccount::query()
            ->where('user_id', $tenantId)
            ->whereNull('owner_user_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (!$adminAccount) {
            return response()->json(['message' => 'Rekening admin belum disiapkan.'], 422);
        }

        $uniqueCode = random_int(11, 199);
        $baseAmount = (float) $validated['amount'];
        $payAmount = $baseAmount + $uniqueCode;

        $requestRecord = MobileTransferRequest::create([
            'user_id' => $tenantId,
            'requester_member_id' => $member->id,
            'target_member_id' => $member->id, // topup ditujukan ke saldo member sendiri
            'kind' => 'topup',
            'unique_code' => $uniqueCode,
            'pay_amount' => $payAmount,
            'bank_account_id' => $adminAccount->id,
            'amount' => $baseAmount,
            'note' => $validated['note'] ?? 'Permintaan topup manual',
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Permintaan topup dicatat. Silakan transfer sesuai instruksi.',
            'topup' => [
                'id' => (int) $requestRecord->id,
                'status' => $requestRecord->status,
                'amount' => $baseAmount,
                'unique_code' => $uniqueCode,
                'pay_amount' => $payAmount,
                'note' => $requestRecord->note,
                'bank_account' => [
                    'id' => (int) $adminAccount->id,
                    'name' => $adminAccount->name,
                    'bank_name' => $adminAccount->bank_name,
                    'account_number' => $adminAccount->account_number,
                ],
            ],
        ], 201);
    }

    public function transfer(Request $request): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request);
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'target_account' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $sender = $this->resolveSelfMember($tenantId);
        if (!$sender) {
            return response()->json(['message' => 'Member koperasi tidak ditemukan.'], 404);
        }

        $receiver = KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->where('member_no', $validated['target_account'])
            ->first();

        if (!$receiver) {
            return response()->json(['message' => 'Rekening tujuan tidak ditemukan.'], 404);
        }

        if ($receiver->id === $sender->id) {
            return response()->json(['message' => 'Tidak dapat transfer ke rekening sendiri.'], 422);
        }

        $wallet = $this->walletService->resolveOwnedWallet(
            $this->walletService->defaultWalletMap($tenantId)['saving'] ?? null,
            $tenantId
        );

        if (!$wallet) {
            return response()->json(['message' => 'Dompet simpanan belum tersedia.'], 422);
        }

        $amount = (float) $validated['amount'];
        $senderBalance = (float) KoperasiSaving::query()
            ->where('koperasi_member_id', $sender->id)
            ->sum('amount');

        if ($amount > $senderBalance) {
            return response()->json(['message' => 'Saldo tidak mencukupi.'], 422);
        }

        $transactionDate = now();

        DB::transaction(function () use ($sender, $receiver, $wallet, $amount, $validated, $transactionDate) {
            KoperasiSaving::create([
                'koperasi_member_id' => $sender->id,
                'wallet_account_id' => $wallet->id,
                'type' => 'transfer_keluar',
                'amount' => -$amount,
                'transaction_date' => $transactionDate,
                'note' => $validated['note'] ?? ('Transfer ke ' . $receiver->name),
            ]);

            KoperasiSaving::create([
                'koperasi_member_id' => $receiver->id,
                'wallet_account_id' => $wallet->id,
                'type' => 'transfer_masuk',
                'amount' => $amount,
                'transaction_date' => $transactionDate,
                'note' => $validated['note'] ?? ('Transfer dari ' . $sender->name),
            ]);
        });

        return response()->json([
            'message' => 'Transfer berhasil.',
            'receiver' => [
                'member_no' => $receiver->member_no,
                'name' => $receiver->name,
            ],
        ]);
    }

    public function requestFunds(Request $request): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request);
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'target_account' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $requester = $this->resolveSelfMember($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Member koperasi tidak ditemukan.'], 404);
        }

        $target = KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->where('member_no', $validated['target_account'])
            ->first();

        if (!$target) {
            return response()->json(['message' => 'Rekening tujuan tidak ditemukan.'], 404);
        }

        if ($target->id === $requester->id) {
            return response()->json(['message' => 'Permintaan tidak boleh ke rekening sendiri.'], 422);
        }

        $record = MobileTransferRequest::create([
            'user_id' => $tenantId,
            'requester_member_id' => $requester->id,
            'target_member_id' => $target->id,
            'amount' => (float) $validated['amount'],
            'note' => $validated['note'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Permintaan saldo dikirim.',
            'request' => [
                'id' => $record->id,
                'amount' => (float) $record->amount,
                'status' => $record->status,
                'target_name' => $target->name,
                'target_member_no' => $target->member_no,
            ],
        ], 201);
    }

    public function notifications(Request $request): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request);
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $memberIds = KoperasiMember::query()->where('user_id', $tenantId)->pluck('id')->all();

        $transactions = KoperasiSaving::query()
            ->whereIn('koperasi_member_id', $memberIds)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (KoperasiSaving $saving) {
                $amount = (float) $saving->amount;
                $title = $amount >= 0 ? 'Transaksi Masuk' : 'Transaksi Keluar';
                $body = ($saving->note ?: 'Transaksi koperasi') . ' • ' . number_format(abs($amount));

                return [
                    'id' => 'tx-' . $saving->id,
                    'title' => $title,
                    'body' => $body,
                    'date' => (string) $saving->transaction_date,
                ];
            });

        $requests = MobileTransferRequest::query()
            ->where('user_id', $tenantId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (MobileTransferRequest $req) {
                $title = $req->kind === 'topup' ? 'Permintaan Topup' : 'Permintaan Saldo';
                $bodyAmount = $req->pay_amount ?: $req->amount;
                return [
                    'id' => 'req-' . $req->id,
                    'title' => $title,
                    'body' => 'Status: ' . $req->status . ' • ' . number_format((float) $bodyAmount),
                    'date' => (string) $req->created_at,
                ];
            });

        $data = $transactions->merge($requests)->sortByDesc('date')->values()->take(30);

        return response()->json([
            'data' => $data,
        ]);
    }

    public function uploadTopupProof(Request $request): JsonResponse
    {
        $accessError = $this->ensureMemberAccess($request);
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'transfer_id' => ['required', 'integer', 'exists:mobile_transfer_requests,id'],
            'proof' => ['required', 'file', 'image', 'max:2048'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $transfer = MobileTransferRequest::query()
            ->where('user_id', $tenantId)
            ->where('id', (int) $validated['transfer_id'])
            ->where('kind', 'topup')
            ->where('status', 'pending')
            ->first();

        if (!$transfer) {
            return response()->json(['message' => 'Topup tidak ditemukan atau sudah diproses.'], 404);
        }

        $path = $request->file('proof')->store('topup-proofs', 'public');

        $transfer->update([
            'proof_path' => $path,
            'proof_submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Bukti transfer diterima. Menunggu verifikasi admin.',
            'proof_url' => asset('storage/' . $path),
        ]);
    }

    private function ensureMemberAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User tidak terautentikasi.'], 401);
        }

        if (!$user->isApproved()) {
            return response()->json(['message' => 'Akun belum aktif untuk mengakses aplikasi mobile.'], 403);
        }

        if (!$user->isCooperativeMode()) {
            return response()->json(['message' => 'Fitur ini hanya untuk mode koperasi.'], 403);
        }

        return null;
    }

    private function resolveSelfMember(int $tenantId): ?KoperasiMember
    {
        $userId = auth()->id();
        if (!$userId) {
            return null;
        }

        $member = KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->where('account_user_id', $userId)
            ->orderBy('id')
            ->first();

        if ($member) {
            return $member;
        }

        $unbound = KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->whereNull('account_user_id')
            ->withSum('savings as total_savings', 'amount')
            ->orderByDesc('total_savings')
            ->orderBy('id')
            ->first();

        if ($unbound) {
            $unbound->account_user_id = $userId;
            $unbound->save();
            return $unbound;
        }

        return null;
    }
}
