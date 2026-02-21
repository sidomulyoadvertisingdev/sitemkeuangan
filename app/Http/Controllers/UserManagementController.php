<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $permissionOptions = User::permissionOptions();

        return view('users.index', compact('users', 'q', 'permissionOptions'));
    }

    public function create()
    {
        $permissionOptions = User::permissionOptions();
        return view('users.create', compact('permissionOptions'));
    }

    public function store(Request $request)
    {
        $permissionKeys = array_keys(User::permissionOptions());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'is_admin' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($permissionKeys)],
        ]);

        $isAdmin = (bool) ($validated['is_admin'] ?? false);
        $permissions = $this->sanitizePermissions($validated['permissions'] ?? [], $permissionKeys);

        if (!$isAdmin && empty($permissions)) {
            return back()->withInput()->withErrors([
                'permissions' => 'Pilih minimal satu hak akses untuk user non-admin.',
            ]);
        }

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $isAdmin,
            'permissions' => $isAdmin ? null : $permissions,
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $permissionOptions = User::permissionOptions();
        return view('users.edit', compact('user', 'permissionOptions'));
    }

    public function update(Request $request, User $user)
    {
        $permissionKeys = array_keys(User::permissionOptions());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'is_admin' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($permissionKeys)],
        ]);

        $isAdmin = (bool) ($validated['is_admin'] ?? false);
        $permissions = $this->sanitizePermissions($validated['permissions'] ?? [], $permissionKeys);

        if (!$isAdmin && empty($permissions)) {
            return back()->withInput()->withErrors([
                'permissions' => 'Pilih minimal satu hak akses untuk user non-admin.',
            ]);
        }

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => $isAdmin,
            'permissions' => $isAdmin ? null : $permissions,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'delete' => 'Anda tidak bisa menghapus akun sendiri.',
            ]);
        }

        if ($user->is_admin) {
            $adminCount = User::where('is_admin', true)->count();
            if ($adminCount <= 1) {
                return back()->withErrors([
                    'delete' => 'Minimal harus ada satu admin aktif.',
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
}
