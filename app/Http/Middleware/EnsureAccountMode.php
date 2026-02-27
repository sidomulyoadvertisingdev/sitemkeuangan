<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountMode
{
    public function handle(Request $request, Closure $next, string $mode): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (!in_array($mode, [User::MODE_ORGANIZATION, User::MODE_COOPERATIVE], true)) {
            abort(403, 'Mode aplikasi tidak valid.');
        }

        if ($user->account_mode !== $mode) {
            $targetRoute = $user->isCooperativeMode() ? 'koperasi.dashboard' : 'dashboard';

            return redirect()
                ->route($targetRoute)
                ->withErrors([
                    'mode' => 'Akun Anda tidak memiliki akses ke modul ini.',
                ]);
        }

        return $next($request);
    }
}
