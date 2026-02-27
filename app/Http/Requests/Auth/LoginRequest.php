<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'login_mode' => ['nullable', 'string', 'in:organization,cooperative'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->string('email')->lower()->toString())->first();

        if ($user && $user->isPendingApproval()) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda masih menunggu persetujuan admin.',
            ]);
        }

        if ($user && $user->isBanned()) {
            $reason = $user->banned_reason ? ' Alasan: ' . $user->banned_reason : '';
            throw ValidationException::withMessages([
                'email' => 'Akun Anda telah diblokir admin.' . $reason,
            ]);
        }

        $requestedMode = $this->string('login_mode')->toString();
        if ($requestedMode === '') {
            $requestedMode = User::MODE_ORGANIZATION;
            $this->merge(['login_mode' => $requestedMode]);
        }

        if ($user && $user->account_mode !== $requestedMode) {
            $targetLabel = $requestedMode === User::MODE_COOPERATIVE ? 'Cooperative Finance' : 'Organizational Finance';
            $userLabel = $user->isCooperativeMode() ? 'Cooperative Finance' : 'Organizational Finance';

            throw ValidationException::withMessages([
                'email' => "Akun ini terdaftar untuk {$userLabel}. Silakan login melalui menu {$userLabel} (bukan {$targetLabel}).",
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $loggedInUser = Auth::user();
        if ($loggedInUser && !$loggedInUser->isApproved()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda tidak aktif. Hubungi admin.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
