<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = (string) $request->bearerToken();

        if ($plainToken === '') {
            return response()->json([
                'message' => 'Token mobile wajib dikirim.',
            ], 401);
        }

        $hashedToken = hash('sha256', $plainToken);
        $accessToken = MobileAccessToken::with('user')
            ->where('token_hash', $hashedToken)
            ->first();

        if (!$accessToken || !$accessToken->user) {
            return response()->json([
                'message' => 'Token tidak valid.',
            ], 401);
        }

        if ($accessToken->expires_at && now()->greaterThan($accessToken->expires_at)) {
            $accessToken->delete();

            return response()->json([
                'message' => 'Token sudah kedaluwarsa. Silakan login ulang.',
            ], 401);
        }

        $user = $accessToken->user;
        if (!$user->isApproved()) {
            $accessToken->delete();

            $message = $user->isBanned()
                ? 'Akun Anda diblokir admin.'
                : 'Akun Anda masih menunggu persetujuan admin.';

            return response()->json(['message' => $message], 403);
        }

        $accessToken->forceFill([
            'last_used_at' => now(),
        ])->save();

        // Pastikan facade Auth mengenal user ini (untuk helper auth())
        Auth::setUser($user);

        $request->attributes->set('mobile_access_token', $accessToken);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
