<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\User;
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

        if ($user->isPendingApproval()) {
            return response()->json([
                'message' => 'Akun Anda masih menunggu persetujuan admin.',
            ], 403);
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

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user' => $this->transformUser($user),
        ]);
    }

    public function me(Request $request)
    {
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
        $permissions = $user->is_admin || $user->is_platform_admin
            ? array_keys(User::PERMISSIONS)
            : ($user->permissions ?? []);

        $role = 'anggota_penabung';
        if ($user->isCooperativeMode()) {
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
        ];
    }
}
