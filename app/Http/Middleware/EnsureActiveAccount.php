<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->isApproved()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $user->isBanned()
            ? 'Akun Anda diblokir admin.'
            : 'Akun Anda masih menunggu persetujuan admin.';

        return redirect()
            ->route('login', ['mode' => $user->account_mode])
            ->withErrors(['email' => $message]);
    }
}
