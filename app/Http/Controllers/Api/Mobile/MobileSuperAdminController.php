<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AccountTransfer;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\IuranInstallment;
use App\Models\IuranMember;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileSuperAdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $year = (int) now()->year;
        $month = (int) now()->month;

        $totalBalance = (float) BankAccount::query()
            ->where('user_id', $tenantId)
            ->sum('balance');

        $totalIncome = (float) Transaction::query()
            ->where('user_id', $tenantId)
            ->where('type', 'income')
            ->sum('amount');

        $totalExpense = (float) Transaction::query()
            ->where('user_id', $tenantId)
            ->where('type', 'expense')
            ->sum('amount');

        $transactionsByMonth = Transaction::query()
            ->where('user_id', $tenantId)
            ->whereYear('date', $year)
            ->get()
            ->groupBy(function (Transaction $transaction) {
                return (int) date('n', strtotime((string) $transaction->date));
            });

        $chart = [];
        for ($index = 1; $index <= 12; $index++) {
            $rows = $transactionsByMonth->get($index, collect());
            $chart[] = [
                'month' => date('M', mktime(0, 0, 0, $index, 1)),
                'income' => (float) $rows->where('type', 'income')->sum('amount'),
                'expense' => (float) $rows->where('type', 'expense')->sum('amount'),
            ];
        }

        $membersQuery = $this->managedUsersQuery($tenantId);
        $projectsQuery = Project::query()->where('user_id', $tenantId);
        $debts = Debt::query()->where('user_id', $tenantId)->get();
        $budgets = Budget::query()
            ->where('user_id', $tenantId)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $iuranTarget = (float) IuranMember::query()
            ->where('user_id', $tenantId)
            ->sum('target_amount');

        $iuranCollected = (float) IuranInstallment::query()
            ->join('iuran_members', 'iuran_members.id', '=', 'iuran_installments.iuran_member_id')
            ->where('iuran_members.user_id', $tenantId)
            ->sum('iuran_installments.amount');

        $recentTransactions = Transaction::query()
            ->with(['category:id,name', 'project:id,name', 'bankAccount:id,name'])
            ->where('user_id', $tenantId)
            ->latest('date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Transaction $transaction) => $this->transformTransaction($transaction))
            ->values();

        return response()->json([
            'total_balance' => $totalBalance,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_balance' => $totalIncome - $totalExpense,
            'wallet_count' => (int) BankAccount::query()->where('user_id', $tenantId)->count(),
            'transaction_count' => (int) Transaction::query()->where('user_id', $tenantId)->count(),
            'project_count' => (int) (clone $projectsQuery)->count(),
            'active_project_count' => (int) (clone $projectsQuery)->where('status', 'ongoing')->count(),
            'budget_count' => (int) $budgets->count(),
            'debt_count' => (int) $debts->where('type', 'hutang')->count(),
            'receivable_count' => (int) $debts->where('type', 'piutang')->count(),
            'iuran_member_count' => (int) IuranMember::query()->where('user_id', $tenantId)->count(),
            'managed_user_count' => (int) (clone $membersQuery)->count(),
            'active_user_count' => (int) (clone $membersQuery)->where('account_status', User::STATUS_APPROVED)->count(),
            'iuran_target' => $iuranTarget,
            'iuran_collected' => $iuranCollected,
            'chart' => $chart,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();

        return response()->json($this->resolveFinanceOptions($tenantId));
    }

    public function members(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $q = trim((string) $request->query('q', ''));

        $members = $this->managedUsersQuery($tenantId)
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%')
                        ->orWhere('organization_name', 'like', '%' . $q . '%');
                });
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $member) => $this->transformManagedUser($member))
            ->values();

        return response()->json([
            'data' => $members,
            'meta' => [
                'total' => $members->count(),
                'query' => $q,
            ],
        ]);
    }

    public function storeMember(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:100'],
            'member_code' => ['nullable', 'string', 'max:100'],
            'joined_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer'],
        ]);

        $actor = $request->user();
        $tenantId = (int) $actor->tenantUserId();
        $temporaryPassword = 'password123';
        $isActive = ($validated['status'] ?? 'active') === 'active';

        $member = User::create([
            'name' => trim((string) $validated['name']),
            'organization_name' => $this->resolveOrganizationName($actor),
            'account_mode' => (string) $actor->account_mode,
            'email' => strtolower((string) $validated['email']),
            'password' => Hash::make($temporaryPassword),
            'is_admin' => false,
            'is_platform_admin' => false,
            'permissions' => ['transactions.manage'],
            'account_status' => $isActive ? User::STATUS_APPROVED : User::STATUS_BANNED,
            'approved_at' => $isActive ? ($validated['joined_at'] ?? now()) : null,
            'approved_by' => $isActive ? $actor->id : null,
            'data_owner_user_id' => $tenantId,
            'invite_quota' => 0,
            'banned_at' => $isActive ? null : now(),
            'banned_reason' => $isActive ? null : 'Dinonaktifkan dari mobile super admin.',
        ]);

        return response()->json([
            'message' => 'User baru berhasil ditambahkan.',
            'temporary_password' => $temporaryPassword,
            'member' => $this->transformManagedUser($member),
        ], 201);
    }

    public function updateMember(Request $request, User $member): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if (!$this->isManagedUser($member, $tenantId)) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $member->id],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $isActive = $validated['status'] === 'active';

        $member->update([
            'name' => trim((string) $validated['name']),
            'email' => strtolower((string) $validated['email']),
            'account_status' => $isActive ? User::STATUS_APPROVED : User::STATUS_BANNED,
            'approved_at' => $isActive ? ($member->approved_at ?? now()) : $member->approved_at,
            'approved_by' => $isActive ? ($member->approved_by ?? $request->user()->id) : $member->approved_by,
            'banned_at' => $isActive ? null : now(),
            'banned_reason' => $isActive ? null : 'Dinonaktifkan dari mobile super admin.',
        ]);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'member' => $this->transformManagedUser($member->fresh()),
        ]);
    }

    public function toggleMemberStatus(User $member, Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if (!$this->isManagedUser($member, $tenantId)) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        $isActive = $member->account_status !== User::STATUS_APPROVED;
        $member->update([
            'account_status' => $isActive ? User::STATUS_APPROVED : User::STATUS_BANNED,
            'approved_at' => $isActive ? now() : $member->approved_at,
            'approved_by' => $isActive ? $request->user()->id : $member->approved_by,
            'banned_at' => $isActive ? null : now(),
            'banned_reason' => $isActive ? null : 'Dinonaktifkan dari mobile super admin.',
        ]);

        return response()->json([
            'message' => $isActive ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.',
            'member' => $this->transformManagedUser($member->fresh()),
        ]);
    }

    public function projects(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $q = trim((string) $request->query('q', ''));

        $projects = Project::query()
            ->where('user_id', $tenantId)
            ->withCount('iuranAssignments')
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('description', 'like', '%' . $q . '%');
                });
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $project) => $this->transformProject($project))
            ->values();

        return response()->json([
            'data' => $projects,
            'meta' => [
                'total' => $projects->count(),
                'query' => $q,
            ],
        ]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:draft,active,completed'],
            'budget' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $project = Project::create([
            'user_id' => $tenantId,
            'bank_account_id' => $this->resolveDefaultBankAccount($tenantId)->id,
            'name' => trim((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'target_amount' => (float) $validated['budget'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'status' => $this->toBackendProjectStatus($validated['status'] ?? 'active'),
        ]);

        $project->loadCount('iuranAssignments');

        return response()->json([
            'message' => 'Proyek baru berhasil dibuat.',
            'project' => $this->transformProject($project),
        ], 201);
    }

    public function updateProject(Request $request, Project $project): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        if ((int) $project->user_id !== (int) $request->user()->tenantUserId()) {
            return response()->json([
                'message' => 'Proyek tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:draft,active,completed'],
            'budget' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
        ]);

        $project->update([
            'name' => trim((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'target_amount' => (float) $validated['budget'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'status' => $this->toBackendProjectStatus($validated['status']),
        ]);

        $project->loadCount('iuranAssignments');

        return response()->json([
            'message' => 'Proyek berhasil diperbarui.',
            'project' => $this->transformProject($project),
        ]);
    }

    public function destroyProject(Request $request, Project $project): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        if ((int) $project->user_id !== (int) $request->user()->tenantUserId()) {
            return response()->json([
                'message' => 'Proyek tidak ditemukan.',
            ], 404);
        }

        if ($project->transactions()->exists() || $project->iuranAssignments()->exists()) {
            return response()->json([
                'message' => 'Proyek tidak bisa dihapus karena sudah memiliki transaksi atau assignment iuran.',
            ], 422);
        }

        $project->delete();

        return response()->json([
            'message' => 'Proyek berhasil dihapus.',
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));

        $transactions = Transaction::query()
            ->with(['category:id,name', 'project:id,name', 'bankAccount:id,name'])
            ->where('user_id', $tenantId)
            ->when(in_array($type, ['income', 'expense'], true), function (Builder $query) use ($type) {
                $query->where('type', $type);
            })
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('note', 'like', '%' . $q . '%')
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', '%' . $q . '%'))
                        ->orWhereHas('project', fn (Builder $project) => $project->where('name', 'like', '%' . $q . '%'))
                        ->orWhereHas('bankAccount', fn (Builder $account) => $account->where('name', 'like', '%' . $q . '%'));
                });
            })
            ->latest('date')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Transaction $transaction) => $this->transformTransaction($transaction))
            ->values();

        return response()->json([
            'data' => $transactions,
            'meta' => [
                'total' => $transactions->count(),
                'query' => $q,
                'type' => $type,
            ],
        ]);
    }

    public function storeTransaction(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $this->validateTransactionPayload($request);
        $tenantId = (int) $request->user()->tenantUserId();
        $category = $this->resolveCategory($tenantId, (int) $validated['category_id'], (string) $validated['type']);
        $bankAccount = $this->resolveBankAccount($tenantId, (int) $validated['bank_account_id']);
        $project = $this->resolveProject($tenantId, (int) ($validated['project_id'] ?? 0), false);

        $this->ensureBudgetCapacity(
            $tenantId,
            (string) $validated['type'],
            (int) $category->id,
            (string) $validated['date'],
            (float) $validated['amount']
        );

        $transaction = DB::transaction(function () use ($tenantId, $validated, $category, $bankAccount, $project) {
            $transaction = Transaction::create([
                'user_id' => $tenantId,
                'type' => $validated['type'],
                'category_id' => $category->id,
                'project_id' => $project?->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => (float) $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            $this->adjustBankBalance(
                $bankAccount,
                $validated['type'] === 'income' ? (float) $validated['amount'] : -(float) $validated['amount']
            );

            return $transaction;
        });

        $transaction->load(['category:id,name', 'project:id,name', 'bankAccount:id,name']);

        return response()->json([
            'message' => 'Transaksi berhasil disimpan.',
            'transaction' => $this->transformTransaction($transaction),
        ], 201);
    }

    public function updateTransaction(Request $request, Transaction $transaction): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if ((int) $transaction->user_id !== $tenantId) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        $validated = $this->validateTransactionPayload($request);
        $category = $this->resolveCategory($tenantId, (int) $validated['category_id'], (string) $validated['type']);
        $bankAccount = $this->resolveBankAccount($tenantId, (int) $validated['bank_account_id']);
        $project = $this->resolveProject($tenantId, (int) ($validated['project_id'] ?? 0), false);

        $this->ensureBudgetCapacity(
            $tenantId,
            (string) $validated['type'],
            (int) $category->id,
            (string) $validated['date'],
            (float) $validated['amount'],
            (int) $transaction->id
        );

        DB::transaction(function () use ($transaction, $validated, $category, $bankAccount, $project) {
            $oldBank = BankAccount::query()->find($transaction->bank_account_id);
            if ($oldBank) {
                $this->adjustBankBalance(
                    $oldBank,
                    $transaction->type === 'income' ? -(float) $transaction->amount : (float) $transaction->amount
                );
            }

            $transaction->update([
                'type' => $validated['type'],
                'category_id' => $category->id,
                'project_id' => $project?->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => (float) $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            $this->adjustBankBalance(
                $bankAccount,
                $validated['type'] === 'income' ? (float) $validated['amount'] : -(float) $validated['amount']
            );
        });

        $transaction->refresh();
        $transaction->load(['category:id,name', 'project:id,name', 'bankAccount:id,name']);

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui.',
            'transaction' => $this->transformTransaction($transaction),
        ]);
    }

    public function destroyTransaction(Request $request, Transaction $transaction): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if ((int) $transaction->user_id !== $tenantId) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($transaction) {
            $bank = BankAccount::query()->find($transaction->bank_account_id);
            if ($bank) {
                $this->adjustBankBalance(
                    $bank,
                    $transaction->type === 'income' ? -(float) $transaction->amount : (float) $transaction->amount
                );
            }

            $transaction->delete();
        });

        return response()->json([
            'message' => 'Transaksi berhasil dihapus.',
        ]);
    }

    public function wallets(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $wallets = BankAccount::query()
            ->where('user_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (BankAccount $wallet) => $this->transformWallet($wallet))
            ->values();

        return response()->json([
            'total_balance' => (float) $wallets->sum('balance'),
            'wallets' => $wallets,
        ]);
    }

    public function storeWallet(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $wallet = BankAccount::create([
            'user_id' => $tenantId,
            'account_kind' => BankAccount::KIND_GENERAL,
            'owner_user_id' => null,
            'name' => trim((string) $validated['name']),
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'balance' => (float) ($validated['balance'] ?? 0),
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ]);

        if ($wallet->is_default) {
            $this->unsetOtherDefaultWallets($tenantId, (int) $wallet->id);
        }

        return response()->json([
            'message' => 'Dompet berhasil ditambahkan.',
            'wallet' => $this->transformWallet($wallet),
        ], 201);
    }

    public function updateWallet(Request $request, BankAccount $wallet): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if ((int) $wallet->user_id !== $tenantId) {
            return response()->json([
                'message' => 'Dompet tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $wallet->update([
            'name' => trim((string) $validated['name']),
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'balance' => array_key_exists('balance', $validated) ? (float) $validated['balance'] : (float) $wallet->balance,
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ]);

        if ($wallet->is_default) {
            $this->unsetOtherDefaultWallets($tenantId, (int) $wallet->id);
        }

        return response()->json([
            'message' => 'Dompet berhasil diperbarui.',
            'wallet' => $this->transformWallet($wallet->fresh()),
        ]);
    }

    public function destroyWallet(Request $request, BankAccount $wallet): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if ((int) $wallet->user_id !== $tenantId) {
            return response()->json([
                'message' => 'Dompet tidak ditemukan.',
            ], 404);
        }

        if ((float) $wallet->balance > 0) {
            return response()->json([
                'message' => 'Dompet tidak bisa dihapus karena masih memiliki saldo.',
            ], 422);
        }

        $hasRelations = Transaction::query()->where('bank_account_id', $wallet->id)->exists()
            || Project::query()->where('bank_account_id', $wallet->id)->exists()
            || AccountTransfer::query()->where('sender_bank_account_id', $wallet->id)->exists()
            || AccountTransfer::query()->where('receiver_bank_account_id', $wallet->id)->exists();

        if ($hasRelations) {
            return response()->json([
                'message' => 'Dompet tidak bisa dihapus karena sudah dipakai transaksi atau transfer.',
            ], 422);
        }

        $wallet->delete();

        return response()->json([
            'message' => 'Dompet berhasil dihapus.',
        ]);
    }

    public function transfers(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $transfers = AccountTransfer::query()
            ->where('sender_user_id', $tenantId)
            ->where('receiver_user_id', $tenantId)
            ->with([
                'senderBankAccount:id,name',
                'receiverBankAccount:id,name',
                'requestedBy:id,name',
                'processedBy:id,name',
            ])
            ->latest('transfer_date')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (AccountTransfer $transfer) => $this->transformTransfer($transfer))
            ->values();

        return response()->json([
            'data' => $transfers,
            'meta' => [
                'total' => $transfers->count(),
            ],
        ]);
    }

    public function storeTransfer(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'from_bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'to_bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $amount = (float) $validated['amount'];

        $transfer = DB::transaction(function () use ($request, $tenantId, $validated, $amount) {
            $fromAccount = $this->resolveBankAccountForUpdate($tenantId, (int) $validated['from_bank_account_id']);
            $toAccount = $this->resolveBankAccountForUpdate($tenantId, (int) $validated['to_bank_account_id']);

            if ((int) $fromAccount->id === (int) $toAccount->id) {
                throw ValidationException::withMessages([
                    'to_bank_account_id' => 'Dompet tujuan harus berbeda dari dompet asal.',
                ]);
            }

            if ($amount > (float) $fromAccount->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo dompet asal tidak cukup.',
                ]);
            }

            $fromAccount->balance -= $amount;
            $fromAccount->save();

            $toAccount->balance += $amount;
            $toAccount->save();

            return AccountTransfer::create([
                'sender_user_id' => $tenantId,
                'receiver_user_id' => $tenantId,
                'sender_bank_account_id' => (int) $fromAccount->id,
                'receiver_bank_account_id' => (int) $toAccount->id,
                'kind' => AccountTransfer::KIND_DIRECT_TRANSFER,
                'status' => AccountTransfer::STATUS_COMPLETED,
                'amount' => $amount,
                'transfer_date' => $validated['transfer_date'],
                'note' => $validated['note'] ?? null,
                'requested_by_user_id' => (int) $request->user()->id,
                'processed_by_user_id' => (int) $request->user()->id,
                'processed_at' => now(),
            ]);
        });

        $transfer->load([
            'senderBankAccount:id,name',
            'receiverBankAccount:id,name',
            'requestedBy:id,name',
            'processedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Transfer berhasil disimpan.',
            'transfer' => $this->transformTransfer($transfer),
        ], 201);
    }

    public function budgets(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        $budgets = Budget::query()
            ->with('category:id,name')
            ->where('user_id', $tenantId)
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('category_id')
            ->get()
            ->map(fn (Budget $budget) => $this->transformBudget($budget, $tenantId))
            ->values();

        return response()->json([
            'data' => $budgets,
            'meta' => [
                'total' => $budgets->count(),
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    public function storeBudget(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'limit' => ['required', 'numeric', 'min:1'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $category = Category::query()
            ->where('id', (int) $validated['category_id'])
            ->where('user_id', $tenantId)
            ->where('type', 'expense')
            ->first();

        if (!$category) {
            return response()->json([
                'message' => 'Kategori budget tidak valid.',
            ], 422);
        }

        $exists = Budget::query()
            ->where('user_id', $tenantId)
            ->where('category_id', $category->id)
            ->where('month', (int) $validated['month'])
            ->where('year', (int) $validated['year'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Budget kategori ini sudah ada di periode tersebut.',
            ], 422);
        }

        $budget = Budget::create([
            'user_id' => $tenantId,
            'category_id' => $category->id,
            'limit' => (float) $validated['limit'],
            'month' => (int) $validated['month'],
            'year' => (int) $validated['year'],
        ]);

        $budget->load('category:id,name');

        return response()->json([
            'message' => 'Budget berhasil ditambahkan.',
            'budget' => $this->transformBudget($budget, $tenantId),
        ], 201);
    }

    public function updateBudget(Request $request, Budget $budget): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if ((int) $budget->user_id !== $tenantId) {
            return response()->json([
                'message' => 'Budget tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'limit' => ['required', 'numeric', 'min:1'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000'],
        ]);

        $category = Category::query()
            ->where('id', (int) $validated['category_id'])
            ->where('user_id', $tenantId)
            ->where('type', 'expense')
            ->first();

        if (!$category) {
            return response()->json([
                'message' => 'Kategori budget tidak valid.',
            ], 422);
        }

        $exists = Budget::query()
            ->where('user_id', $tenantId)
            ->where('category_id', $category->id)
            ->where('month', (int) $validated['month'])
            ->where('year', (int) $validated['year'])
            ->where('id', '!=', $budget->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Budget kategori ini sudah ada di periode tersebut.',
            ], 422);
        }

        $budget->update([
            'category_id' => $category->id,
            'limit' => (float) $validated['limit'],
            'month' => (int) $validated['month'],
            'year' => (int) $validated['year'],
        ]);

        $budget->load('category:id,name');

        return response()->json([
            'message' => 'Budget berhasil diperbarui.',
            'budget' => $this->transformBudget($budget, $tenantId),
        ]);
    }

    public function destroyBudget(Request $request, Budget $budget): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        if ((int) $budget->user_id !== (int) $request->user()->tenantUserId()) {
            return response()->json([
                'message' => 'Budget tidak ditemukan.',
            ], 404);
        }

        $budget->delete();

        return response()->json([
            'message' => 'Budget berhasil dihapus.',
        ]);
    }

    public function debts(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $type = trim((string) $request->query('type', ''));
        $q = trim((string) $request->query('q', ''));

        $debts = Debt::query()
            ->with('installments')
            ->where('user_id', $tenantId)
            ->when(in_array($type, ['hutang', 'piutang'], true), function (Builder $query) use ($type) {
                $query->where('type', $type);
            })
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('note', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('status')
            ->orderBy('due_date')
            ->get()
            ->map(fn (Debt $debt) => $this->transformDebt($debt))
            ->values();

        return response()->json([
            'data' => $debts,
            'meta' => [
                'total' => $debts->count(),
                'type' => $type,
                'query' => $q,
            ],
        ]);
    }

    public function storeDebt(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:hutang,piutang'],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $debt = Debt::create([
            'user_id' => (int) $request->user()->tenantUserId(),
            'type' => $validated['type'],
            'name' => trim((string) $validated['name']),
            'amount' => (float) $validated['amount'],
            'due_date' => $validated['due_date'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => 'belum_lunas',
        ]);

        return response()->json([
            'message' => 'Data hutang/piutang berhasil ditambahkan.',
            'debt' => $this->transformDebt($debt->fresh('installments')),
        ], 201);
    }

    public function updateDebt(Request $request, Debt $debt): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        if ((int) $debt->user_id !== (int) $request->user()->tenantUserId()) {
            return response()->json([
                'message' => 'Data hutang/piutang tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:hutang,piutang'],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $paidAmount = (float) $debt->installments()->sum('amount');
        $status = $paidAmount >= (float) $validated['amount'] ? 'lunas' : 'belum_lunas';

        $debt->update([
            'type' => $validated['type'],
            'name' => trim((string) $validated['name']),
            'amount' => (float) $validated['amount'],
            'due_date' => $validated['due_date'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Data hutang/piutang berhasil diperbarui.',
            'debt' => $this->transformDebt($debt->fresh('installments')),
        ]);
    }

    public function destroyDebt(Request $request, Debt $debt): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        if ((int) $debt->user_id !== (int) $request->user()->tenantUserId()) {
            return response()->json([
                'message' => 'Data hutang/piutang tidak ditemukan.',
            ], 404);
        }

        $debt->delete();

        return response()->json([
            'message' => 'Data hutang/piutang berhasil dihapus.',
        ]);
    }

    public function storeDebtInstallment(Request $request, Debt $debt): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        if ((int) $debt->user_id !== $tenantId) {
            return response()->json([
                'message' => 'Data hutang/piutang tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'paid_at' => ['required', 'date'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'note' => ['nullable', 'string'],
        ]);

        $bankAccount = $this->resolveBankAccount($tenantId, (int) $validated['bank_account_id']);
        $categoryType = $debt->type === 'piutang' ? 'income' : 'expense';
        $category = Category::query()
            ->where('id', (int) $validated['category_id'])
            ->where('user_id', $tenantId)
            ->where('type', $categoryType)
            ->first();

        if (!$category) {
            return response()->json([
                'message' => 'Kategori pembayaran tidak valid.',
            ], 422);
        }

        $remaining = max(0, (float) $debt->amount - (float) $debt->installments()->sum('amount'));
        if ((float) $validated['amount'] > $remaining && $remaining > 0) {
            return response()->json([
                'message' => 'Nominal pembayaran melebihi sisa hutang/piutang.',
                'remaining_amount' => $remaining,
            ], 422);
        }

        $installment = DB::transaction(function () use ($tenantId, $debt, $validated, $bankAccount, $category) {
            $installment = DebtInstallment::create([
                'debt_id' => $debt->id,
                'bank_account_id' => $bankAccount->id,
                'category_id' => $category->id,
                'amount' => (float) $validated['amount'],
                'paid_at' => $validated['paid_at'],
                'note' => $validated['note'] ?? null,
            ]);

            $transactionType = $debt->type === 'piutang' ? 'income' : 'expense';

            Transaction::create([
                'user_id' => $tenantId,
                'type' => $transactionType,
                'category_id' => $category->id,
                'project_id' => null,
                'bank_account_id' => $bankAccount->id,
                'amount' => (float) $installment->amount,
                'date' => $installment->paid_at,
                'note' => $installment->note ?: ('Pembayaran ' . $debt->name),
            ]);

            $this->adjustBankBalance(
                $bankAccount,
                $transactionType === 'income' ? (float) $installment->amount : -(float) $installment->amount
            );

            $paidAmount = (float) $debt->installments()->sum('amount') + (float) $installment->amount;
            $debt->update([
                'status' => $paidAmount >= (float) $debt->amount ? 'lunas' : 'belum_lunas',
            ]);

            return $installment;
        });

        return response()->json([
            'message' => 'Pembayaran hutang/piutang berhasil dicatat.',
            'installment' => [
                'id' => (int) $installment->id,
                'amount' => (float) $installment->amount,
                'paid_at' => (string) $installment->paid_at,
                'note' => $installment->note,
            ],
            'debt' => $this->transformDebt($debt->fresh('installments')),
        ], 201);
    }

    public function reports(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $defaultStartDate = now()->startOfMonth()->toDateString();
        $defaultEndDate = now()->toDateString();
        $startDate = (string) $request->query('start_date', $defaultStartDate);
        $endDate = (string) $request->query('end_date', $defaultEndDate);

        validator(
            ['start_date' => $startDate, 'end_date' => $endDate],
            ['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date']]
        )->validate();

        $membersQuery = $this->managedUsersQuery($tenantId);
        $projectsQuery = Project::query()->where('user_id', $tenantId)->withCount('iuranAssignments');
        $transactions = Transaction::query()
            ->with(['category:id,name', 'project:id,name', 'bankAccount:id,name'])
            ->where('user_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->latest('date')
            ->latest('id')
            ->get();

        $transfers = AccountTransfer::query()
            ->with(['senderBankAccount:id,name', 'receiverBankAccount:id,name'])
            ->where('sender_user_id', $tenantId)
            ->where('receiver_user_id', $tenantId)
            ->whereBetween('transfer_date', [$startDate, $endDate])
            ->latest('transfer_date')
            ->latest('id')
            ->get();

        $month = (int) date('n', strtotime($endDate));
        $year = (int) date('Y', strtotime($endDate));
        $budgets = Budget::query()
            ->with('category:id,name')
            ->where('user_id', $tenantId)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $debts = Debt::query()
            ->with('installments')
            ->where('user_id', $tenantId)
            ->get();

        $iuranTarget = (float) IuranMember::query()->where('user_id', $tenantId)->sum('target_amount');
        $iuranCollected = (float) IuranInstallment::query()
            ->join('iuran_members', 'iuran_members.id', '=', 'iuran_installments.iuran_member_id')
            ->where('iuran_members.user_id', $tenantId)
            ->whereBetween('iuran_installments.paid_at', [$startDate, $endDate])
            ->sum('iuran_installments.amount');

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_members' => (clone $membersQuery)->count(),
            'active_members' => (clone $membersQuery)->where('account_status', User::STATUS_APPROVED)->count(),
            'total_projects' => (clone $projectsQuery)->count(),
            'active_projects' => (clone $projectsQuery)->where('status', 'ongoing')->count(),
            'total_budget' => (float) (clone $projectsQuery)->sum('target_amount'),
            'assigned_members' => (int) (clone $projectsQuery)->get()->sum('iuran_assignments_count'),
            'total_balance' => (float) BankAccount::query()->where('user_id', $tenantId)->sum('balance'),
            'total_income' => (float) $transactions->where('type', 'income')->sum('amount'),
            'total_expense' => (float) $transactions->where('type', 'expense')->sum('amount'),
            'net_balance' => (float) $transactions->where('type', 'income')->sum('amount') - (float) $transactions->where('type', 'expense')->sum('amount'),
            'budget_items' => $budgets->map(fn (Budget $budget) => $this->transformBudget($budget, $tenantId))->values(),
            'debt_items' => $debts->map(fn (Debt $debt) => $this->transformDebt($debt))->values(),
            'transaction_items' => $transactions->take(20)->map(fn (Transaction $transaction) => $this->transformTransaction($transaction))->values(),
            'transfer_items' => $transfers->take(20)->map(fn (AccountTransfer $transfer) => $this->transformTransfer($transfer))->values(),
            'total_budgets' => (int) $budgets->count(),
            'total_budget_limit' => (float) $budgets->sum('limit'),
            'total_budget_used' => (float) $budgets->sum(function (Budget $budget) use ($tenantId) {
                return Transaction::query()
                    ->where('user_id', $tenantId)
                    ->where('type', 'expense')
                    ->where('category_id', $budget->category_id)
                    ->whereMonth('date', $budget->month)
                    ->whereYear('date', $budget->year)
                    ->sum('amount');
            }),
            'total_debt_balance' => (float) $debts->where('type', 'hutang')->sum(function (Debt $debt) {
                return max(0, (float) $debt->amount - (float) $debt->installments->sum('amount'));
            }),
            'total_receivable_balance' => (float) $debts->where('type', 'piutang')->sum(function (Debt $debt) {
                return max(0, (float) $debt->amount - (float) $debt->installments->sum('amount'));
            }),
            'iuran_target' => $iuranTarget,
            'iuran_collected' => $iuranCollected,
            'recent_members' => (clone $membersQuery)->latest('created_at')->limit(5)->get()->map(fn (User $member) => $this->transformManagedUser($member))->values(),
            'recent_projects' => (clone $projectsQuery)->latest('created_at')->limit(5)->get()->map(fn (Project $project) => $this->transformProject($project))->values(),
        ]);
    }

    private function ensureSuperAdminAccess(?User $user): ?JsonResponse
    {
        if (!$user) {
            return response()->json([
                'message' => 'User tidak terautentikasi.',
            ], 401);
        }

        if (
            !$user->is_admin &&
            !$user->is_platform_admin &&
            !$user->hasPermission('users.manage') &&
            !$user->hasPermission('projects.manage') &&
            !$user->hasPermission('reports.view')
        ) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke fitur super admin mobile.',
            ], 403);
        }

        return null;
    }

    private function managedUsersQuery(int $tenantId): Builder
    {
        return User::query()
            ->where('data_owner_user_id', $tenantId)
            ->where('is_admin', false)
            ->where('is_platform_admin', false);
    }

    private function isManagedUser(User $member, int $tenantId): bool
    {
        return !$member->is_admin
            && !$member->is_platform_admin
            && (int) $member->data_owner_user_id === $tenantId;
    }

    private function validateTransactionPayload(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'in:income,expense'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);
    }

    private function resolveFinanceOptions(int $tenantId): array
    {
        $this->ensureDefaultCategories($tenantId);
        $this->resolveDefaultBankAccount($tenantId);

        return [
            'bank_accounts' => BankAccount::query()
                ->where('user_id', $tenantId)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'balance', 'account_kind'])
                ->map(fn (BankAccount $account) => [
                    'id' => (int) $account->id,
                    'name' => (string) $account->name,
                    'balance' => (float) $account->balance,
                    'account_kind' => (string) $account->account_kind,
                ])
                ->values(),
            'income_categories' => Category::query()
                ->where('user_id', $tenantId)
                ->where('type', 'income')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $category) => [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                ])
                ->values(),
            'expense_categories' => Category::query()
                ->where('user_id', $tenantId)
                ->where('type', 'expense')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $category) => [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                ])
                ->values(),
            'projects' => Project::query()
                ->where('user_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (Project $project) => [
                    'id' => (int) $project->id,
                    'name' => (string) $project->name,
                    'status' => $this->toMobileProjectStatus((string) $project->status),
                ])
                ->values(),
        ];
    }

    private function resolveCategory(int $tenantId, int $categoryId, string $type): Category
    {
        $category = Category::query()
            ->where('id', $categoryId)
            ->where('user_id', $tenantId)
            ->where('type', $type)
            ->first();

        if (!$category) {
            throw ValidationException::withMessages([
                'category_id' => 'Kategori transaksi tidak valid.',
            ]);
        }

        return $category;
    }

    private function resolveBankAccount(int $tenantId, int $bankAccountId): BankAccount
    {
        $bankAccount = BankAccount::query()
            ->where('user_id', $tenantId)
            ->where('id', $bankAccountId)
            ->first();

        if (!$bankAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Dompet/rekening tidak valid.',
            ]);
        }

        return $bankAccount;
    }

    private function resolveBankAccountForUpdate(int $tenantId, int $bankAccountId): BankAccount
    {
        $bankAccount = BankAccount::query()
            ->where('user_id', $tenantId)
            ->where('id', $bankAccountId)
            ->lockForUpdate()
            ->first();

        if (!$bankAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Dompet/rekening tidak valid.',
            ]);
        }

        return $bankAccount;
    }

    private function resolveProject(int $tenantId, int $projectId, bool $required): ?Project
    {
        if ($projectId <= 0) {
            if ($required) {
                throw ValidationException::withMessages([
                    'project_id' => 'Proyek wajib dipilih.',
                ]);
            }

            return null;
        }

        $project = Project::query()
            ->where('user_id', $tenantId)
            ->where('id', $projectId)
            ->first();

        if (!$project) {
            throw ValidationException::withMessages([
                'project_id' => 'Proyek tidak valid.',
            ]);
        }

        return $project;
    }

    private function ensureBudgetCapacity(
        int $tenantId,
        string $type,
        int $categoryId,
        string $date,
        float $amount,
        ?int $ignoreTransactionId = null
    ): void {
        if ($type !== 'expense') {
            return;
        }

        $budget = Budget::query()
            ->where('user_id', $tenantId)
            ->where('category_id', $categoryId)
            ->where('month', (int) date('n', strtotime($date)))
            ->where('year', (int) date('Y', strtotime($date)))
            ->first();

        if (!$budget) {
            return;
        }

        $used = Transaction::query()
            ->where('user_id', $tenantId)
            ->where('type', 'expense')
            ->where('category_id', $categoryId)
            ->whereMonth('date', $budget->month)
            ->whereYear('date', $budget->year)
            ->when($ignoreTransactionId, function (Builder $query) use ($ignoreTransactionId) {
                $query->where('id', '!=', $ignoreTransactionId);
            })
            ->sum('amount');

        if (((float) $used + $amount) > (float) $budget->limit) {
            throw ValidationException::withMessages([
                'amount' => 'Nilai transaksi melebihi limit budget kategori ini.',
            ]);
        }
    }

    private function ensureDefaultCategories(int $tenantId): void
    {
        $pairs = [
            ['name' => 'Pemasukan Umum', 'type' => 'income'],
            ['name' => 'Pengeluaran Umum', 'type' => 'expense'],
        ];

        foreach ($pairs as $pair) {
            Category::query()->firstOrCreate([
                'user_id' => $tenantId,
                'name' => $pair['name'],
                'type' => $pair['type'],
            ]);
        }
    }

    private function resolveOrganizationName(User $actor): string
    {
        $dataOwner = $actor->dataOwner;

        return (string) ($dataOwner?->organization_name ?: $actor->organization_name ?: 'Mobile Organization');
    }

    private function resolveDefaultBankAccount(int $tenantId): BankAccount
    {
        $account = BankAccount::query()
            ->where('user_id', $tenantId)
            ->where('account_kind', BankAccount::KIND_GENERAL)
            ->whereNull('owner_user_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($account) {
            return $account;
        }

        return BankAccount::create([
            'user_id' => $tenantId,
            'account_kind' => BankAccount::KIND_GENERAL,
            'owner_user_id' => null,
            'name' => 'Kas Mobile Utama',
            'bank_name' => 'Kas Internal',
            'account_number' => 'MOBILE-' . $tenantId,
            'balance' => 0,
            'is_default' => true,
        ]);
    }

    private function unsetOtherDefaultWallets(int $tenantId, int $exceptId): void
    {
        BankAccount::query()
            ->where('user_id', $tenantId)
            ->where('id', '!=', $exceptId)
            ->update(['is_default' => false]);
    }

    private function adjustBankBalance(BankAccount $bankAccount, float $delta): void
    {
        $bankAccount->balance += $delta;
        $bankAccount->save();
    }

    private function transformManagedUser(User $member): array
    {
        return [
            'id' => (int) $member->id,
            'name' => (string) $member->name,
            'email' => (string) $member->email,
            'phone' => null,
            'member_code' => 'USR-' . str_pad((string) $member->id, 4, '0', STR_PAD_LEFT),
            'status' => $member->account_status === User::STATUS_APPROVED ? 'active' : 'inactive',
            'account_status' => (string) $member->account_status,
            'joined_at' => optional($member->approved_at ?? $member->created_at)?->toDateString(),
            'project_ids' => [],
            'project_names' => [],
            'permissions' => $member->permissions ?? [],
        ];
    }

    private function transformProject(Project $project): array
    {
        return [
            'id' => (int) $project->id,
            'name' => (string) $project->name,
            'code' => 'PRJ-' . str_pad((string) $project->id, 4, '0', STR_PAD_LEFT),
            'status' => $this->toMobileProjectStatus((string) $project->status),
            'budget' => (float) $project->target_amount,
            'start_date' => (string) $project->start_date,
            'end_date' => $project->end_date,
            'description' => $project->description,
            'member_count' => (int) ($project->iuran_assignments_count ?? 0),
        ];
    }

    private function transformTransaction(Transaction $transaction): array
    {
        return [
            'id' => (int) $transaction->id,
            'type' => (string) $transaction->type,
            'category_id' => (int) $transaction->category_id,
            'category_name' => (string) ($transaction->category?->name ?? '-'),
            'project_id' => $transaction->project_id ? (int) $transaction->project_id : null,
            'project_name' => $transaction->project?->name,
            'bank_account_id' => (int) $transaction->bank_account_id,
            'bank_account_name' => (string) ($transaction->bankAccount?->name ?? '-'),
            'amount' => (float) $transaction->amount,
            'date' => (string) $transaction->date,
            'note' => $transaction->note,
        ];
    }

    private function transformWallet(BankAccount $wallet): array
    {
        return [
            'id' => (int) $wallet->id,
            'name' => (string) $wallet->name,
            'bank_name' => $wallet->bank_name,
            'account_number' => $wallet->account_number,
            'balance' => (float) $wallet->balance,
            'is_default' => (bool) $wallet->is_default,
            'account_kind' => (string) ($wallet->account_kind ?? BankAccount::KIND_GENERAL),
        ];
    }

    private function transformTransfer(AccountTransfer $transfer): array
    {
        return [
            'id' => (int) $transfer->id,
            'kind' => (string) $transfer->kind,
            'status' => (string) $transfer->status,
            'amount' => (float) $transfer->amount,
            'transfer_date' => (string) $transfer->transfer_date,
            'note' => $transfer->note,
            'sender_bank_account_id' => (int) $transfer->sender_bank_account_id,
            'sender_bank_account_name' => (string) ($transfer->senderBankAccount?->name ?? '-'),
            'receiver_bank_account_id' => (int) $transfer->receiver_bank_account_id,
            'receiver_bank_account_name' => (string) ($transfer->receiverBankAccount?->name ?? '-'),
            'requested_by' => (string) ($transfer->requestedBy?->name ?? '-'),
            'processed_by' => (string) ($transfer->processedBy?->name ?? '-'),
        ];
    }

    private function transformBudget(Budget $budget, int $tenantId): array
    {
        $used = (float) Transaction::query()
            ->where('user_id', $tenantId)
            ->where('type', 'expense')
            ->where('category_id', $budget->category_id)
            ->whereMonth('date', $budget->month)
            ->whereYear('date', $budget->year)
            ->sum('amount');

        $remaining = max(0, (float) $budget->limit - $used);

        return [
            'id' => (int) $budget->id,
            'category_id' => (int) $budget->category_id,
            'category_name' => (string) ($budget->category?->name ?? '-'),
            'limit' => (float) $budget->limit,
            'used' => $used,
            'remaining' => $remaining,
            'percentage' => (float) ((float) $budget->limit > 0 ? round(($used / (float) $budget->limit) * 100, 2) : 0),
            'month' => (int) $budget->month,
            'year' => (int) $budget->year,
        ];
    }

    private function transformDebt(Debt $debt): array
    {
        $paidAmount = (float) $debt->installments->sum('amount');
        $remainingAmount = max(0, (float) $debt->amount - $paidAmount);

        return [
            'id' => (int) $debt->id,
            'type' => (string) $debt->type,
            'name' => (string) $debt->name,
            'amount' => (float) $debt->amount,
            'status' => (string) $debt->status,
            'due_date' => $debt->due_date,
            'note' => $debt->note,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'installments_count' => (int) $debt->installments->count(),
        ];
    }

    private function toBackendProjectStatus(string $status): string
    {
        return match ($status) {
            'active' => 'ongoing',
            'completed' => 'done',
            default => 'draft',
        };
    }

    private function toMobileProjectStatus(string $status): string
    {
        return match ($status) {
            'ongoing' => 'active',
            'done', 'cancelled' => 'completed',
            default => 'draft',
        };
    }
}
