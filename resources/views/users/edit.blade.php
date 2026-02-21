@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="card">
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Nama</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="form-group">
                <label>Password Baru (opsional)</label>
                <input type="password" name="password" class="form-control">
            </div>

            <div class="form-group">
                <label>Konfirmasi Password Baru</label>
                <input type="password" name="password_confirmation" class="form-control">
            </div>

            <div class="form-group form-check">
                <input type="hidden" name="is_admin" value="0">
                <input type="checkbox"
                       name="is_admin"
                       id="is_admin"
                       value="1"
                       class="form-check-input"
                       {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_admin">Jadikan Admin (akses penuh)</label>
            </div>

            <div class="form-group" id="permissionBox">
                <label>Hak Akses User</label>
                @php
                    $selectedPermissions = old('permissions', $user->permissions ?? []);
                @endphp
                @foreach($permissionOptions as $key => $label)
                    <div class="form-check">
                        <input type="checkbox"
                               name="permissions[]"
                               id="perm_{{ $loop->index }}"
                               value="{{ $key }}"
                               class="form-check-input"
                               {{ in_array($key, $selectedPermissions) ? 'checked' : '' }}>
                        <label class="form-check-label" for="perm_{{ $loop->index }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>

            <button class="btn btn-primary">Update</button>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const adminCheckbox = document.getElementById('is_admin');
        const permissionBox = document.getElementById('permissionBox');

        function togglePermissions() {
            if (!adminCheckbox || !permissionBox) return;
            permissionBox.style.display = adminCheckbox.checked ? 'none' : '';
        }

        adminCheckbox?.addEventListener('change', togglePermissions);
        togglePermissions();
    })();
</script>
@endpush
