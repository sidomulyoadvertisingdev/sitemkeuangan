<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AccountTransfer;
use App\Models\ActivityLog;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\IuranInstallment;
use App\Models\IuranMember;
use App\Models\ProjectIuranAssignment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\IuranTargetSynchronizer;
use App\Services\OfficerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MobileIuranController extends Controller
{
    public function __construct(
        private IuranTargetSynchronizer $iuranTargetSynchronizer,
        private OfficerWalletService $officerWalletService
    )
    {
    }

    public function members(Request $request): JsonResponse
    {
        $accessError = $this->ensureIuranAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $actor = $request->user();
        $q = trim((string) $request->query('q', ''));
        $tenantId = $actor->tenantUserId();
        $this->iuranTargetSynchronizer->syncForTenant((int) $tenantId);
        $hasAssignmentTable = Schema::hasTable('project_iuran_assignments');

        $membersQuery = IuranMember::withSum('installments as paid_amount', 'amount')
            ->where('user_id', $tenantId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%');
            });

        if ($hasAssignmentTable) {
            $membersQuery->with([
                'projectAssignments' => function ($query) use ($actor, $tenantId) {
                    $query->with([
                        'project:id,name,user_id',
                        'officer:id,name',
                    ])->whereHas('project', function ($project) use ($tenantId) {
                        $project->where('user_id', $tenantId);
                    });

                    if ($this->isRestrictedOfficer($actor)) {
                        $query->where('officer_user_id', $actor->id);
                    }
                },
            ]);
        }

        if ($this->isRestrictedOfficer($actor) && $hasAssignmentTable) {
            $membersQuery->whereExists(function ($query) use ($actor, $tenantId) {
                $query->select(DB::raw(1))
                    ->from('project_iuran_assignments')
                    ->join('projects', 'projects.id', '=', 'project_iuran_assignments.project_id')
                    ->whereColumn('project_iuran_assignments.iuran_member_id', 'iuran_members.id')
                    ->where('project_iuran_assignments.officer_user_id', $actor->id)
                    ->where('projects.user_id', $tenantId);
            });
        }

        $members = $membersQuery
            ->orderByRaw("CASE WHEN status = 'lunas' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(fn (IuranMember $member) => $this->transformMember($member))
            ->values();

        return response()->json([
            'data' => $members,
            'meta' => [
                'total' => $members->count(),
                'query' => $q,
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $accessError = $this->ensureIuranAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $actor = $request->user();
        $tenantId = $actor->tenantUserId();
        $isRestrictedOfficer = $this->isRestrictedOfficer($actor);

        if ($isRestrictedOfficer) {
            $wallet = $this->officerWalletService->resolveForUser($actor);
            $accounts = collect([[
                'id' => (int) $wallet->id,
                'name' => (string) $wallet->name,
                'balance' => (float) $wallet->balance,
                'account_kind' => (string) $wallet->account_kind,
            ]]);
        } else {
            $accounts = BankAccount::where('user_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'balance', 'account_kind'])
                ->map(function (BankAccount $account) {
                    return [
                        'id' => (int) $account->id,
                        'name' => (string) $account->name,
                        'balance' => (float) $account->balance,
                        'account_kind' => (string) $account->account_kind,
                    ];
                })
                ->values();
        }

        $adminAccounts = $this->officerWalletService->adminAccounts((int) $tenantId)
            ->map(function (BankAccount $account) {
                return [
                    'id' => (int) $account->id,
                    'name' => (string) $account->name,
                    'balance' => (float) $account->balance,
                    'is_default' => (bool) $account->is_default,
                ];
            })
            ->values();

        $categories = Category::where('user_id', $tenantId)
            ->where('type', 'income')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'bank_accounts' => $accounts,
            'admin_accounts' => $adminAccounts,
            'income_categories' => $categories,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $accessError = $this->ensureIuranAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $actor = $request->user();
        $q = trim((string) $request->query('q', ''));
        $tenantId = $actor->tenantUserId();
        $this->iuranTargetSynchronizer->syncForTenant((int) $tenantId);

        $rowsQuery = IuranInstallment::query()
            ->join('iuran_members', 'iuran_members.id', '=', 'iuran_installments.iuran_member_id')
            ->where('iuran_members.user_id', $tenantId)
            ->select(
                'iuran_installments.id',
                'iuran_installments.iuran_member_id',
                'iuran_installments.amount',
                'iuran_installments.paid_at',
                'iuran_installments.note',
                'iuran_members.name as member_name'
            )
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('iuran_members.name', 'like', '%' . $q . '%')
                        ->orWhere('iuran_installments.note', 'like', '%' . $q . '%');
                });
            });

        if ($this->isRestrictedOfficer($actor) && Schema::hasTable('project_iuran_assignments')) {
            $rowsQuery->whereExists(function ($query) use ($actor, $tenantId) {
                $query->select(DB::raw(1))
                    ->from('project_iuran_assignments')
                    ->join('projects', 'projects.id', '=', 'project_iuran_assignments.project_id')
                    ->whereColumn('project_iuran_assignments.iuran_member_id', 'iuran_installments.iuran_member_id')
                    ->where('project_iuran_assignments.officer_user_id', $actor->id)
                    ->where('projects.user_id', $tenantId);
            });
        }

        $rows = $rowsQuery
            ->orderByDesc('iuran_installments.paid_at')
            ->orderByDesc('iuran_installments.id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $rows->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'member_id' => (int) $row->iuran_member_id,
                    'member_name' => (string) $row->member_name,
                    'amount' => (float) $row->amount,
                    'paid_at' => (string) $row->paid_at,
                    'note' => $row->note,
                ];
            })->values(),
            'meta' => [
                'total' => $rows->count(),
                'query' => $q,
            ],
        ]);
    }

    public function storeInstallment(Request $request, IuranMember $member): JsonResponse
    {
        $accessError = $this->ensureIuranAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = $request->user()->tenantUserId();
        if ((int) $member->user_id !== $tenantId) {
            return response()->json([
                'message' => 'Data anggota tidak ditemukan.',
            ], 404);
        }
        $this->iuranTargetSynchronizer->syncForTenant((int) $tenantId, [(int) $member->id]);
        $member->refresh();

        $actor = $request->user();
        $assignmentProjectId = null;
        if ($this->isRestrictedOfficer($actor) && Schema::hasTable('project_iuran_assignments')) {
            $assignmentProjectId = ProjectIuranAssignment::query()
                ->join('projects', 'projects.id', '=', 'project_iuran_assignments.project_id')
                ->where('project_iuran_assignments.iuran_member_id', $member->id)
                ->where('project_iuran_assignments.officer_user_id', $actor->id)
                ->where('projects.user_id', $tenantId)
                ->orderByDesc('project_iuran_assignments.id')
                ->value('project_iuran_assignments.project_id');

            if (!$assignmentProjectId) {
                return response()->json([
                    'message' => 'Anggota ini belum ditugaskan ke Anda.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'paid_at' => ['required', 'date'],
            'bank_account_id' => $this->isRestrictedOfficer($actor) ? ['nullable', 'integer'] : ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'note' => ['nullable', 'string'],
        ]);

        if ($this->isRestrictedOfficer($actor)) {
            $bank = $this->officerWalletService->resolveForUser($actor);
        } else {
            $bank = BankAccount::where('id', (int) ($validated['bank_account_id'] ?? 0))
                ->where('user_id', $tenantId)
                ->first();
        }
        if (!$bank) {
            return response()->json([
                'message' => 'Rekening bank tidak valid.',
            ], 422);
        }

        $category = Category::where('id', (int) $validated['category_id'])
            ->where('user_id', $tenantId)
            ->where('type', 'income')
            ->first();
        if (!$category) {
            return response()->json([
                'message' => 'Kategori pemasukan tidak valid.',
            ], 422);
        }

        $amount = (float) $validated['amount'];
        $paid = (float) $member->installments()->sum('amount');
        $remaining = max(0, (float) $member->target_amount - $paid);

        if ($remaining <= 0) {
            return response()->json([
                'message' => 'Target iuran anggota ini sudah tercapai.',
            ], 422);
        }

        if ($amount > $remaining) {
            return response()->json([
                'message' => 'Nominal iuran melebihi sisa iuran anggota.',
                'remaining_amount' => $remaining,
            ], 422);
        }

        $paidYear = (int) date('Y', strtotime((string) $validated['paid_at']));
        if ($paidYear < (int) $member->target_start_year || $paidYear > (int) $member->target_end_year) {
            return response()->json([
                'message' => 'Tanggal bayar harus berada pada periode target ' . $member->target_period . '.',
            ], 422);
        }

        $installment = DB::transaction(function () use ($member, $bank, $category, $amount, $validated, $tenantId, $assignmentProjectId, $actor) {
            $record = IuranInstallment::create([
                'iuran_member_id' => $member->id,
                'bank_account_id' => $bank->id,
                'category_id' => $category->id,
                'officer_user_id' => (int) $actor->id,
                'project_id' => $assignmentProjectId ?: null,
                'amount' => $amount,
                'paid_at' => $validated['paid_at'],
                'note' => $validated['note'] ?? null,
            ]);

            Transaction::create([
                'user_id' => $tenantId,
                'type' => 'income',
                'category_id' => $category->id,
                'project_id' => $assignmentProjectId ?: null,
                'bank_account_id' => $bank->id,
                'amount' => $record->amount,
                'date' => $record->paid_at,
                'note' => $record->note ?: ('Iuran anggota: ' . $member->name),
            ]);

            $bank->balance += $amount;
            $bank->save();

            $latestPaid = (float) $member->installments()->sum('amount');
            $member->update([
                'status' => $latestPaid >= (float) $member->target_amount ? 'lunas' : 'aktif',
            ]);

            return $record;
        });

        $member->refresh();
        $member->loadSum('installments as paid_amount', 'amount');

        return response()->json([
            'message' => 'Iuran berhasil disimpan.',
            'member' => $this->transformMember($member),
            'installment' => [
                'id' => $installment->id,
                'amount' => (float) $installment->amount,
                'paid_at' => (string) $installment->paid_at,
                'note' => $installment->note,
            ],
        ], 201);
    }

    public function wallet(Request $request): JsonResponse
    {
        $accessError = $this->ensureIuranAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $actor = $request->user();
        if (!$this->isRestrictedOfficer($actor)) {
            return response()->json([
                'message' => 'Fitur dompet petugas khusus untuk user petugas.',
            ], 403);
        }

        $tenantId = $actor->tenantUserId();
        $wallet = $this->officerWalletService->resolveForUser($actor);
        $adminAccounts = $this->officerWalletService->adminAccounts((int) $tenantId)
            ->map(function (BankAccount $account) {
                return [
                    'id' => (int) $account->id,
                    'name' => (string) $account->name,
                    'balance' => (float) $account->balance,
                    'is_default' => (bool) $account->is_default,
                ];
            })
            ->values();

        $transfers = AccountTransfer::query()
            ->where('sender_user_id', $tenantId)
            ->where('receiver_user_id', $tenantId)
            ->where(function ($query) use ($wallet) {
                $query->where('sender_bank_account_id', (int) $wallet->id)
                    ->orWhere('receiver_bank_account_id', (int) $wallet->id);
            })
            ->with([
                'senderBankAccount:id,name',
                'receiverBankAccount:id,name',
                'requestedBy:id,name',
                'processedBy:id,name',
            ])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AccountTransfer $transfer) => $this->transformWalletTransfer($transfer, (int) $wallet->id))
            ->values();

        return response()->json([
            'wallet' => [
                'id' => (int) $wallet->id,
                'name' => (string) $wallet->name,
                'balance' => (float) $wallet->balance,
            ],
            'admin_accounts' => $adminAccounts,
            'recent_transfers' => $transfers,
        ]);
    }

    public function transferToAdmin(Request $request): JsonResponse
    {
        $accessError = $this->ensureIuranAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $actor = $request->user();
        if (!$this->isRestrictedOfficer($actor)) {
            return response()->json([
                'message' => 'Fitur transfer dompet ini hanya untuk user petugas.',
            ], 403);
        }

        $validated = $request->validate([
            'receiver_bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenantId = $actor->tenantUserId();
        $amount = (float) $validated['amount'];

        $transfer = DB::transaction(function () use ($actor, $tenantId, $validated, $amount) {
            $wallet = BankAccount::query()
                ->where('user_id', $tenantId)
                ->where('account_kind', BankAccount::KIND_OFFICER_WALLET)
                ->where('owner_user_id', (int) $actor->id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = $this->officerWalletService->resolveForUser($actor);
                $wallet = BankAccount::query()
                    ->where('id', (int) $wallet->id)
                    ->where('user_id', $tenantId)
                    ->lockForUpdate()
                    ->first();
            }

            $receiverAccount = BankAccount::query()
                ->where('id', (int) $validated['receiver_bank_account_id'])
                ->where('user_id', $tenantId)
                ->where('account_kind', BankAccount::KIND_GENERAL)
                ->whereNull('owner_user_id')
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw ValidationException::withMessages([
                    'wallet' => 'Dompet petugas tidak ditemukan.',
                ]);
            }

            if (!$receiverAccount) {
                throw ValidationException::withMessages([
                    'receiver_bank_account_id' => 'Rekening admin tujuan tidak valid.',
                ]);
            }

            if ((int) $wallet->id === (int) $receiverAccount->id) {
                throw ValidationException::withMessages([
                    'receiver_bank_account_id' => 'Rekening tujuan harus berbeda dari dompet petugas.',
                ]);
            }

            if ($amount > (float) $wallet->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo dompet petugas tidak cukup.',
                ]);
            }

            $wallet->balance -= $amount;
            $wallet->save();

            $receiverAccount->balance += $amount;
            $receiverAccount->save();

            $note = trim((string) ($validated['note'] ?? ''));
            $baseNote = '[SETOR PETUGAS] Setoran iuran dari dompet petugas.';
            $fullNote = $note !== '' ? $baseNote . ' Catatan: ' . $note : $baseNote;

            $transfer = AccountTransfer::create([
                'sender_user_id' => (int) $tenantId,
                'receiver_user_id' => (int) $tenantId,
                'sender_bank_account_id' => (int) $wallet->id,
                'receiver_bank_account_id' => (int) $receiverAccount->id,
                'kind' => AccountTransfer::KIND_DIRECT_TRANSFER,
                'status' => AccountTransfer::STATUS_COMPLETED,
                'amount' => $amount,
                'transfer_date' => $validated['transfer_date'],
                'note' => $fullNote,
                'requested_by_user_id' => (int) $actor->id,
                'processed_by_user_id' => (int) $actor->id,
                'processed_at' => now(),
            ]);

            ActivityLog::create([
                'tenant_user_id' => (int) $tenantId,
                'actor_user_id' => (int) $actor->id,
                'action' => 'created',
                'subject_type' => AccountTransfer::class,
                'subject_id' => (int) $transfer->id,
                'description' => sprintf(
                    '%s menyetorkan dana iuran Rp %s ke rekening admin %s',
                    (string) $actor->name,
                    number_format($amount, 0, ',', '.'),
                    (string) $receiverAccount->name
                ),
                'meta' => [
                    'kind' => (string) $transfer->kind,
                    'status' => (string) $transfer->status,
                    'amount' => (float) $transfer->amount,
                    'transfer_date' => (string) $transfer->transfer_date,
                    'sender_bank_account_id' => (int) $wallet->id,
                    'receiver_bank_account_id' => (int) $receiverAccount->id,
                    'note' => (string) $transfer->note,
                ],
            ]);

            return $transfer;
        });

        $transfer->load([
            'senderBankAccount:id,name',
            'receiverBankAccount:id,name',
            'requestedBy:id,name',
            'processedBy:id,name',
        ]);

        $wallet = $this->officerWalletService->resolveForUser($actor);

        return response()->json([
            'message' => 'Transfer setoran ke admin berhasil.',
            'wallet_balance' => (float) $wallet->balance,
            'transfer' => $this->transformWalletTransfer($transfer, (int) $wallet->id),
        ], 201);
    }

    private function ensureIuranAccess(?User $user): ?JsonResponse
    {
        if (!$user) {
            return response()->json([
                'message' => 'User tidak terautentikasi.',
            ], 401);
        }

        if (!$user->isOrganizationMode()) {
            return response()->json([
                'message' => 'Fitur mobile iuran hanya untuk mode Organizational Finance.',
            ], 403);
        }

        if (!$user->hasPermission('iuran.manage')) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke fitur iuran.',
            ], 403);
        }

        return null;
    }

    private function isRestrictedOfficer(User $user): bool
    {
        return !$user->is_admin && !$user->is_platform_admin;
    }

    private function transformWalletTransfer(AccountTransfer $transfer, int $walletAccountId): array
    {
        $isOutgoing = (int) $transfer->sender_bank_account_id === $walletAccountId;

        return [
            'id' => (int) $transfer->id,
            'kind' => (string) $transfer->kind,
            'status' => (string) $transfer->status,
            'amount' => (float) $transfer->amount,
            'transfer_date' => (string) $transfer->transfer_date,
            'note' => $transfer->note,
            'direction' => $isOutgoing ? 'outgoing' : 'incoming',
            'sender_account_name' => (string) ($transfer->senderBankAccount?->name ?? '-'),
            'receiver_account_name' => (string) ($transfer->receiverBankAccount?->name ?? '-'),
            'requested_by' => (string) ($transfer->requestedBy?->name ?? '-'),
            'processed_by' => (string) ($transfer->processedBy?->name ?? '-'),
            'processed_at' => $transfer->processed_at?->toIso8601String(),
        ];
    }

    private function transformMember(IuranMember $member): array
    {
        $paid = (float) ($member->paid_amount ?? 0);
        $target = (float) $member->target_amount;
        $remaining = max(0, $target - $paid);
        $progress = $target > 0 ? min(100, round(($paid / $target) * 100)) : 0;
        $assignments = collect();

        if ($member->relationLoaded('projectAssignments')) {
            $assignments = $member->projectAssignments
                ->map(function ($assignment) {
                    return [
                        'project_id' => (int) $assignment->project_id,
                        'project_name' => (string) ($assignment->project?->name ?? '-'),
                        'officer_id' => (int) $assignment->officer_user_id,
                        'officer_name' => (string) ($assignment->officer?->name ?? '-'),
                        'member_class' => (string) ($assignment->member_class ?? 'C'),
                        'class_percent' => (float) ($assignment->class_percent ?? 0),
                        'planned_amount' => (float) ($assignment->planned_amount ?? 0),
                        'note' => $assignment->note,
                    ];
                })
                ->values();
        }

        return [
            'id' => $member->id,
            'name' => $member->name,
            'status' => $member->status,
            'target_amount' => $target,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'target_start_year' => (int) $member->target_start_year,
            'target_end_year' => (int) $member->target_end_year,
            'target_period' => $member->target_period,
            'progress' => (int) $progress,
            'note' => $member->note,
            'assignments' => $assignments,
        ];
    }
}
