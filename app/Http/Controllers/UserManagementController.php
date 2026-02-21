<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $users = $this->managedUsersQuery()
            ->with(['dataOwner:id,name,organization_name'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%')
                        ->orWhere('organization_name', 'like', '%' . $q . '%');
                });
            })
            ->when(in_array($status, [User::STATUS_PENDING, User::STATUS_APPROVED, User::STATUS_BANNED], true), function ($query) use ($status) {
                $query->where('account_status', $status);
            })
            ->orderByRaw("CASE WHEN account_status = 'pending' THEN 0 WHEN account_status = 'banned' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $permissionOptions = $this->permissionOptionsForManagedUsers();
        $statusOptions = [
            User::STATUS_PENDING => 'Menunggu Persetujuan',
            User::STATUS_APPROVED => 'Disetujui',
            User::STATUS_BANNED => 'Diblokir',
        ];

        return view('users.index', compact('users', 'q', 'status', 'permissionOptions', 'statusOptions'));
    }

    public function create()
    {
        $permissionOptions = $this->permissionOptionsForManagedUsers();
        $accessOrganizations = $this->accessOrganizationOptions();
        $canChooseAccessOrganization = auth()->user()->is_platform_admin;
        $canManageInviteQuota = $canChooseAccessOrganization;
        $currentAccessOrganization = $this->resolveAccessOrganization(auth()->user()->tenantUserId());

        return view('users.create', compact(
            'permissionOptions',
            'accessOrganizations',
            'canChooseAccessOrganization',
            'canManageInviteQuota',
            'currentAccessOrganization'
        ));
    }

    public function store(Request $request)
    {
        $actor = auth()->user();
        $isPlatformAdmin = (bool) $actor->is_platform_admin;
        $permissionKeys = array_keys($this->permissionOptionsForManagedUsers());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'organization_name' => 'required|string|max:150',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'is_admin' => 'nullable|boolean',
            'data_owner_user_id' => 'nullable|integer|exists:users,id',
            'invite_quota' => 'nullable|integer|min:0|max:100000',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($permissionKeys)],
        ]);

        $isAdmin = (bool) ($validated['is_admin'] ?? false);
        $isOwnerCreation = $isAdmin && $isPlatformAdmin;
        $inviteQuota = $this->sanitizeInviteQuota($validated['invite_quota'] ?? null);
        $permissions = $this->sanitizePermissions($validated['permissions'] ?? [], $permissionKeys);
        $selectedDataOwnerId = $validated['data_owner_user_id'] ?? null;

        if (!$isPlatformAdmin) {
            $selectedDataOwnerId = $actor->tenantUserId();
        }

        if (!$isAdmin && empty($permissions)) {
            return back()->withInput()->withErrors([
                'permissions' => 'Pilih minimal satu hak akses untuk user non-admin.',
            ]);
        }

        $dataOwner = null;
        if (!$isOwnerCreation) {
            $dataOwner = $this->resolveAccessOrganization($selectedDataOwnerId);
            if (!$dataOwner) {
                return back()->withInput()->withErrors([
                    'data_owner_user_id' => 'Pilih perkumpulan yang valid untuk akses user.',
                ]);
            }

            $this->ensureInviteQuotaAvailable($dataOwner);
        }

        $organizationName = $isOwnerCreation
            ? $validated['organization_name']
            : (string) ($dataOwner->organization_name ?? $validated['organization_name']);

        $user = User::create([
            'name' => $validated['name'],
            'organization_name' => $organizationName,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $isAdmin,
            'is_platform_admin' => false,
            'permissions' => $isAdmin ? null : $permissions,
            'account_status' => User::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'data_owner_user_id' => $isOwnerCreation
                ? null
                : (int) $dataOwner->id,
            'invite_quota' => $isOwnerCreation ? $inviteQuota : null,
        ]);

        if ($isOwnerCreation) {
            $user->update(['data_owner_user_id' => $user->id]);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $this->ensureCanManageUser($user);

        $permissionOptions = $this->permissionOptionsForManagedUsers();
        $accessOrganizations = $this->accessOrganizationOptions();
        $canChooseAccessOrganization = auth()->user()->is_platform_admin;
        $canManageInviteQuota = auth()->user()->is_platform_admin
            && !$user->is_platform_admin
            && $user->is_admin
            && (int) $user->data_owner_user_id === (int) $user->id;
        $currentAccessOrganization = $this->resolveAccessOrganization(auth()->user()->tenantUserId());

        return view('users.edit', compact(
            'user',
            'permissionOptions',
            'accessOrganizations',
            'canChooseAccessOrganization',
            'canManageInviteQuota',
            'currentAccessOrganization'
        ));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureCanManageUser($user);

        $actor = auth()->user();
        $isPlatformAdmin = (bool) $actor->is_platform_admin;
        $permissionKeys = array_keys($this->permissionOptionsForManagedUsers());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'organization_name' => 'required|string|max:150',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'is_admin' => 'nullable|boolean',
            'data_owner_user_id' => 'nullable|integer|exists:users,id',
            'invite_quota' => 'nullable|integer|min:0|max:100000',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($permissionKeys)],
        ]);

        $isAdmin = (bool) ($validated['is_admin'] ?? false);
        $inviteQuota = $this->sanitizeInviteQuota($validated['invite_quota'] ?? null);
        $permissions = $this->sanitizePermissions($validated['permissions'] ?? [], $permissionKeys);
        $selectedDataOwnerId = $validated['data_owner_user_id'] ?? null;

        if (!$isPlatformAdmin) {
            $selectedDataOwnerId = $actor->tenantUserId();
        }

        if (!$isAdmin && empty($permissions)) {
            return back()->withInput()->withErrors([
                'permissions' => 'Pilih minimal satu hak akses untuk user non-admin.',
            ]);
        }

        $dataOwner = null;
        if (!$isAdmin || !$isPlatformAdmin) {
            $dataOwner = $this->resolveAccessOrganization($selectedDataOwnerId);
            if (!$dataOwner) {
                return back()->withInput()->withErrors([
                    'data_owner_user_id' => 'Pilih perkumpulan yang valid untuk akses user.',
                ]);
            }
        }

        if ($user->is_platform_admin) {
            $isAdmin = true;
            $permissions = [];
        }

        $isOwnerAccount = $isAdmin && $isPlatformAdmin;
        $targetOwnerId = $isOwnerAccount
            ? (int) $user->id
            : (int) ($dataOwner?->id ?? 0);

        if ($targetOwnerId !== (int) $user->id && $dataOwner) {
            $this->ensureInviteQuotaAvailable($dataOwner, $user);
        }

        $organizationName = $isOwnerAccount
            ? $validated['organization_name']
            : (string) ($dataOwner->organization_name ?? $validated['organization_name']);

        $data = [
            'name' => $validated['name'],
            'organization_name' => $organizationName,
            'email' => $validated['email'],
            'is_admin' => $isAdmin,
            'permissions' => $isAdmin ? null : $permissions,
            'data_owner_user_id' => $isOwnerAccount
                ? $user->id
                : (int) $dataOwner->id,
        ];

        if ($isPlatformAdmin && !$user->is_platform_admin) {
            $data['invite_quota'] = $isOwnerAccount ? $inviteQuota : null;
        }

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function approve(User $user)
    {
        $this->ensureCanManageUser($user);

        if ($user->isBanned()) {
            return back()->withErrors([
                'approve' => 'Akun yang diblokir harus di-unban terlebih dahulu.',
            ]);
        }

        if ($user->isApproved()) {
            return back()->with('success', 'Akun sudah berstatus disetujui.');
        }

        $actor = auth()->user();
        $isPlatformAdmin = (bool) $actor->is_platform_admin;

        $user->update([
            'account_status' => User::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'is_admin' => $isPlatformAdmin ? true : $user->is_admin,
            'permissions' => $isPlatformAdmin ? null : ($user->permissions ?? []),
            'data_owner_user_id' => $isPlatformAdmin ? $user->id : $actor->tenantUserId(),
        ]);

        return back()->with(
            'success',
            $isPlatformAdmin
                ? 'Akun berhasil disetujui dan diberi akses Super Admin fitur.'
                : 'Akun berhasil disetujui.'
        );
    }

    public function ban(Request $request, User $user)
    {
        $this->ensureCanManageUser($user);

        if (auth()->user()->id === $user->id) {
            return back()->withErrors([
                'ban' => 'Anda tidak bisa memblokir akun sendiri.',
            ]);
        }

        if ($user->is_platform_admin) {
            return back()->withErrors([
                'ban' => 'Platform admin tidak dapat diblokir.',
            ]);
        }

        $validated = $request->validate([
            'banned_reason' => 'nullable|string|max:255',
        ]);

        $user->update([
            'account_status' => User::STATUS_BANNED,
            'banned_at' => now(),
            'banned_reason' => $validated['banned_reason'] ?? null,
        ]);

        return back()->with('success', 'Akun berhasil diblokir.');
    }

    public function unban(User $user)
    {
        $this->ensureCanManageUser($user);

        if (!$user->isBanned()) {
            return back()->with('success', 'Akun tidak dalam status diblokir.');
        }

        $user->update([
            'account_status' => User::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => auth()->user()->id,
            'banned_at' => null,
            'banned_reason' => null,
        ]);

        return back()->with('success', 'Akun berhasil diaktifkan kembali.');
    }

    public function destroy(User $user)
    {
        $this->ensureCanManageUser($user);

        if (auth()->user()->id === $user->id) {
            return back()->withErrors([
                'delete' => 'Anda tidak bisa menghapus akun sendiri.',
            ]);
        }

        if ($user->is_platform_admin) {
            $platformAdminCount = User::where('is_platform_admin', true)->count();
            if ($platformAdminCount <= 1) {
                return back()->withErrors([
                    'delete' => 'Minimal harus ada satu platform admin aktif.',
                ]);
            }
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    private function sanitizePermissions(array $permissions, array $allowed): array
    {
        return array_values(array_intersect(array_unique($permissions), $allowed));
    }

    private function sanitizeInviteQuota($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function permissionOptionsForManagedUsers(): array
    {
        return collect(User::permissionOptions())
            ->except(['users.manage'])
            ->all();
    }

    private function accessOrganizationOptions()
    {
        $actor = auth()->user();

        return User::query()
            ->where('is_platform_admin', false)
            ->where('is_admin', true)
            ->where('account_status', User::STATUS_APPROVED)
            ->when(!$actor->is_platform_admin, function ($query) use ($actor) {
                $query->where('id', $actor->tenantUserId());
            })
            ->orderBy('organization_name')
            ->orderBy('name')
            ->get(['id', 'name', 'organization_name']);
    }

    private function resolveAccessOrganization(?int $id): ?User
    {
        if (!$id) {
            return null;
        }

        $actor = auth()->user();

        return User::query()
            ->where('id', $id)
            ->where('is_platform_admin', false)
            ->where('is_admin', true)
            ->where('account_status', User::STATUS_APPROVED)
            ->when(!$actor->is_platform_admin, function ($query) use ($actor) {
                $query->where('id', $actor->tenantUserId());
            })
            ->first();
    }

    private function managedUsersQuery(): Builder
    {
        $actor = auth()->user();

        return User::query()
            ->when(!$actor->is_platform_admin, function ($query) use ($actor) {
                $query->where('is_platform_admin', false)
                    ->where('data_owner_user_id', $actor->tenantUserId());
            });
    }

    private function ensureCanManageUser(User $user): void
    {
        $actor = auth()->user();

        if ($actor->is_platform_admin) {
            return;
        }

        if ($user->is_platform_admin || (int) $user->data_owner_user_id !== (int) $actor->tenantUserId()) {
            abort(403, 'Anda tidak memiliki hak akses untuk user ini.');
        }
    }

    private function ensureInviteQuotaAvailable(User $dataOwner, ?User $ignoreUser = null): void
    {
        if ($dataOwner->invite_quota === null) {
            return;
        }

        $query = User::query()
            ->where('is_platform_admin', false)
            ->where('data_owner_user_id', $dataOwner->id)
            ->where('id', '!=', $dataOwner->id);

        if ($ignoreUser) {
            $query->where('id', '!=', $ignoreUser->id);
        }

        $usedSlots = (int) $query->count();
        $quota = (int) $dataOwner->invite_quota;

        if ($usedSlots >= $quota) {
            throw ValidationException::withMessages([
                'data_owner_user_id' => "Kuota user perkumpulan {$dataOwner->organization_name} sudah penuh ({$quota} user).",
            ]);
        }
    }
}
