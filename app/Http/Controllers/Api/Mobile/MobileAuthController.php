<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\User;
use App\Models\KoperasiMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'login_mode' => ['nullable', 'string', 'in:organization,cooperative'],
        ]);

        $user = User::where('email', Str::lower((string) $validated['email']))->first();
        if (!$user || !Hash::check((string) $validated['password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Email atau password tidak valid.',
            ], 422);
        }

        if ($user->isBanned()) {
            $reason = $user->banned_reason ? ' Alasan: ' . $user->banned_reason : '';

            return response()->json([
                'message' => 'Akun Anda telah diblokir admin.' . $reason,
            ], 403);
        }

        $requestedMode = (string) ($validated['login_mode'] ?? $user->account_mode);
        if ($user->account_mode !== $requestedMode) {
            $userLabel = $user->isCooperativeMode() ? 'Cooperative Finance' : 'Organizational Finance';

            return response()->json([
                'message' => "Akun ini terdaftar untuk {$userLabel}.",
            ], 403);
        }

        $deviceName = trim((string) ($validated['device_name'] ?? 'expo-mobile'));
        if ($deviceName === '') {
            $deviceName = 'expo-mobile';
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $user->mobileAccessTokens()
            ->where('name', $deviceName)
            ->delete();

        MobileAccessToken::create([
            'user_id' => $user->id,
            'name' => $deviceName,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(30),
        ]);

        $this->ensureMemberLinked($user);

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user' => $this->transformUser($user),
        ]);
    }

    public function register(Request $request)
    {
        return response()->json([
            'message' => 'Pendaftaran online dinonaktifkan. Silakan hubungi admin koperasi untuk dibuatkan akun.',
        ], 403);
    }

    public function me(Request $request)
    {
        $this->ensureMemberLinked($request->user());
        return response()->json([
            'user' => $this->transformUser($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->attributes->get('mobile_access_token');
        if ($token instanceof MobileAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    private function transformUser(User $user): array
    {
        // Aplikasi mobile: berikan akses penuh fitur front-end tanpa perlu atur user management manual
        $permissions = array_keys(User::PERMISSIONS);

        $role = 'anggota_penabung';

        if (
            $user->is_admin ||
            $user->is_platform_admin ||
            in_array('users.manage', $permissions, true) ||
            in_array('projects.manage', $permissions, true)
        ) {
            $role = 'super_admin';
        } elseif ($user->isCooperativeMode()) {
            $role = in_array('koperasi.manage', $permissions, true)
                ? 'petugas_koperasi'
                : 'anggota_penabung';
        } else {
            $role = in_array('iuran.manage', $permissions, true)
                ? 'petugas_organisasi'
                : 'anggota_penabung';
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'organization_name' => $user->organization_name,
            'account_mode' => $user->account_mode,
            'permissions' => $permissions,
            'role' => $role,
            'tenant_user_id' => $user->tenantUserId(),
            'cooperative_id' => $user->data_owner_user_id ?: $user->id,
            'cooperative_code' => $user->cooperative_code,
            'member' => $this->memberInfo($user),
        ];
    }

    private function memberInfo(User $user): ?array
    {
        $member = KoperasiMember::query()
            ->where('user_id', $user->tenantUserId())
            ->where('account_user_id', $user->id)
            ->orderBy('id')
            ->first();

        if (!$member) {
            return null;
        }

        return [
            'id' => (int) $member->id,
            'member_no' => $member->member_no,
            'name' => $member->name,
            'status' => $member->status,
            'join_date' => optional($member->join_date)->toDateString(),
        ];
    }

    private function ensureMemberLinked(User $user): void
    {
        if (!$user->isCooperativeMode()) {
            return;
        }

        $tenantId = (int) $user->tenantUserId();
        $member = KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->where('account_user_id', $user->id)
            ->orderBy('id')
            ->first();

        if ($member) {
            return;
        }

        $unbound = KoperasiMember::query()
            ->where('user_id', $tenantId)
            ->whereNull('account_user_id')
            ->withSum('savings as total_savings', 'amount')
            ->orderByDesc('total_savings')
            ->orderBy('id')
            ->first();

        if ($unbound) {
            $unbound->account_user_id = $user->id;
            if (!$unbound->member_no) {
                $unbound->member_no = KoperasiMember::generateUniqueAccountNumber();
            }
            if (!$unbound->join_date) {
                $unbound->join_date = now();
            }
            $unbound->save();
            return;
        }

        KoperasiMember::create([
            'user_id' => $tenantId,
            'account_user_id' => $user->id,
            'member_no' => KoperasiMember::generateUniqueAccountNumber(),
            'name' => $user->name,
            'status' => $user->isApproved() ? 'aktif' : 'nonaktif',
            'join_date' => now(),
        ]);
    }
}
