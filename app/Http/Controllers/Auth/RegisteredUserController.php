<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'organization_name' => $request->organization_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_admin' => false,
            'is_platform_admin' => false,
            'permissions' => [],
            'account_status' => User::STATUS_PENDING,
        ]);

        event(new Registered($user));

        return redirect()
            ->route('login')
            ->with('status', 'Pendaftaran berhasil. Akun Anda menunggu persetujuan admin.');
    }
}
