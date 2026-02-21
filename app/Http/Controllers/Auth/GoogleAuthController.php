<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.google.client_id');
        $redirectUri = (string) config('services.google.redirect');

        if ($clientId === '' || $redirectUri === '') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Konfigurasi login Google belum lengkap. Hubungi admin.',
                ]);
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        $providedState = (string) $request->query('state', '');

        if ($expectedState === '' || !hash_equals($expectedState, $providedState)) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Sesi login Google tidak valid. Coba lagi.']);
        }

        if ($request->has('error')) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Login Google dibatalkan atau gagal.']);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Kode otorisasi Google tidak ditemukan.']);
        }

        $tokenResponse = Http::asForm()
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ]);

        if (!$tokenResponse->ok()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Gagal mengambil token Google.']);
        }

        $idToken = (string) data_get($tokenResponse->json(), 'id_token', '');
        $accessToken = (string) data_get($tokenResponse->json(), 'access_token', '');

        $googleProfile = $this->fetchGoogleProfile($idToken, $accessToken);
        if ($googleProfile === null) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Profil Google tidak dapat diverifikasi.']);
        }

        $email = strtolower((string) data_get($googleProfile, 'email', ''));
        $googleId = (string) data_get($googleProfile, 'sub', '');
        $emailVerified = filter_var(data_get($googleProfile, 'email_verified', false), FILTER_VALIDATE_BOOL);

        if ($email === '' || $googleId === '' || !$emailVerified) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Email Google belum terverifikasi.']);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Email Google belum terdaftar di sistem ini.']);
        }

        $googleAlreadyLinked = User::where('google_id', $googleId)
            ->where('id', '!=', $user->id)
            ->exists();
        if ($googleAlreadyLinked) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun Google ini sudah terhubung ke user lain.']);
        }

        if ($user->isPendingApproval()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun Anda masih menunggu persetujuan admin.']);
        }

        if ($user->isBanned()) {
            $reason = $user->banned_reason ? ' Alasan: ' . $user->banned_reason : '';
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun Anda diblokir admin.' . $reason]);
        }

        $user->update([
            'google_id' => $googleId,
            'google_linked_at' => now(),
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function fetchGoogleProfile(string $idToken, string $accessToken): ?array
    {
        if ($idToken !== '') {
            $tokenInfoResponse = Http::timeout(15)
                ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

            if ($tokenInfoResponse->ok()) {
                return $tokenInfoResponse->json();
            }
        }

        if ($accessToken !== '') {
            $userInfoResponse = Http::withToken($accessToken)
                ->timeout(15)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');

            if ($userInfoResponse->ok()) {
                return $userInfoResponse->json();
            }
        }

        return null;
    }
}
