<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileSuperAdminController extends Controller
{
    public function members(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();
        $q = trim((string) $request->query('q', ''));

        $members = $this->managedUsersQuery($tenantId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%');
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

        if (!$request->user()->hasPermission('users.manage')) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk menambah anggota.',
            ], 403);
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
            'name' => $validated['name'],
            'organization_name' => $this->resolveOrganizationName($actor),
            'account_mode' => (string) $actor->account_mode,
            'email' => strtolower((string) $validated['email']),
            'password' => Hash::make($temporaryPassword),
            'is_admin' => false,
            'is_platform_admin' => false,
            'permissions' => ['transactions.manage'],
            'account_status' => $isActive ? User::STATUS_APPROVED : User::STATUS_PENDING,
            'approved_at' => $isActive ? ($validated['joined_at'] ?? now()) : null,
            'approved_by' => $isActive ? $actor->id : null,
            'data_owner_user_id' => $tenantId,
            'invite_quota' => 0,
        ]);

        return response()->json([
            'message' => 'Anggota baru berhasil ditambahkan.',
            'temporary_password' => $temporaryPassword,
            'member' => $this->transformManagedUser($member),
        ], 201);
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
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
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

        if (!$request->user()->hasPermission('projects.manage')) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk membuat proyek.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:draft,active,completed'],
            'budget' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $tenantId = (int) $request->user()->tenantUserId();
        $project = Project::create([
            'user_id' => $tenantId,
            'bank_account_id' => $this->resolveDefaultBankAccount($tenantId)->id,
            'name' => $validated['name'],
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

    public function reports(Request $request): JsonResponse
    {
        $accessError = $this->ensureSuperAdminAccess($request->user());
        if ($accessError !== null) {
            return $accessError;
        }

        $tenantId = (int) $request->user()->tenantUserId();

        $membersQuery = $this->managedUsersQuery($tenantId);
        $projectsQuery = Project::query()->where('user_id', $tenantId)->withCount('iuranAssignments');

        return response()->json([
            'total_members' => (clone $membersQuery)->count(),
            'active_members' => (clone $membersQuery)->where('account_status', User::STATUS_APPROVED)->count(),
            'total_projects' => (clone $projectsQuery)->count(),
            'active_projects' => (clone $projectsQuery)->where('status', 'ongoing')->count(),
            'total_budget' => (float) (clone $projectsQuery)->sum('target_amount'),
            'assigned_members' => (int) (clone $projectsQuery)->get()->sum('iuran_assignments_count'),
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

    private function managedUsersQuery(int $tenantId)
    {
        return User::query()
            ->where('data_owner_user_id', $tenantId)
            ->where('is_admin', false)
            ->where('is_platform_admin', false);
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
            'joined_at' => optional($member->approved_at ?? $member->created_at)?->toDateString(),
            'project_ids' => [],
            'project_names' => [],
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
            'start_date' => $project->start_date,
            'end_date' => $project->end_date,
            'description' => $project->description,
            'member_count' => (int) ($project->iuran_assignments_count ?? 0),
        ];
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
