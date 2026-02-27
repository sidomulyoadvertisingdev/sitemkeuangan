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
    public function create(Request $request): View
    {
        $mode = (string) $request->query('mode', User::MODE_ORGANIZATION);
        if (!in_array($mode, [User::MODE_ORGANIZATION, User::MODE_COOPERATIVE], true)) {
            $mode = User::MODE_ORGANIZATION;
        }

        return view('auth.register', compact('mode'));
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
            'account_mode' => ['required', 'string', 'in:organization,cooperative'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'organization_name' => $request->organization_name,
            'account_mode' => $request->account_mode,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_admin' => false,
            'is_platform_admin' => false,
            'permissions' => [],
            'account_status' => User::STATUS_PENDING,
        ]);

        event(new Registered($user));

        return redirect()
            ->route('login', ['mode' => $request->account_mode])
            ->with('status', 'Pendaftaran berhasil. Akun Anda menunggu persetujuan admin.');
    }
}
