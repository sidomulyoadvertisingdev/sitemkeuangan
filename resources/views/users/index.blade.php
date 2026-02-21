@extends('layouts.app')

@section('title', 'Manajemen User')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center">
        <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm mr-2 mb-2">
            <i class="fas fa-user-plus"></i> Tambah User
        </a>

        <form method="GET" action="{{ route('users.index') }}" class="form-inline mb-2">
            <input type="text"
                   name="q"
                   class="form-control form-control-sm mr-2"
                   placeholder="Cari nama / email..."
                   value="{{ $q }}">
            <button class="btn btn-outline-primary btn-sm mr-2" type="submit">
                <i class="fas fa-search"></i> Cari
            </button>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </form>
    </div>

    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger mb-3">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Hak Akses</th>
                        <th width="140">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="badge {{ $user->is_admin ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $user->is_admin ? 'Admin' : 'User' }}
                                </span>
                            </td>
                            <td>
                                @if($user->is_admin)
                                    Semua Akses
                                @else
                                    @php
                                        $labels = collect($user->permissions ?? [])
                                            ->map(fn ($key) => $permissionOptions[$key] ?? $key)
                                            ->values();
                                    @endphp
                                    {{ $labels->isEmpty() ? '-' : $labels->implode(', ') }}
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('users.destroy', $user) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Hapus user ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">Belum ada data user</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
