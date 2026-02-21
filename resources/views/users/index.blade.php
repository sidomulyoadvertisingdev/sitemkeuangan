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
                   placeholder="Cari nama / email / perkumpulan..."
                   value="{{ $q }}">
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">Semua Status</option>
                @foreach($statusOptions as $key => $label)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
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
                        <th>Perkumpulan</th>
                        <th>Akses Data</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Kuota Invite</th>
                        <th>Status Akun</th>
                        <th>Hak Akses</th>
                        <th width="280">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->organization_name ?? '-' }}</td>
                            <td>
                                @if($user->is_platform_admin)
                                    Semua Perkumpulan
                                @elseif($user->is_admin)
                                    Perkumpulan Sendiri
                                @else
                                    {{ $user->dataOwner?->organization_name ?? '-' }}
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="badge {{ $user->is_platform_admin ? 'badge-dark' : ($user->is_admin ? 'badge-success' : 'badge-secondary') }}">
                                    {{ $user->is_platform_admin ? 'Platform Admin' : ($user->is_admin ? 'Super Admin' : 'User') }}
                                </span>
                            </td>
                            <td>
                                @if(!$user->is_platform_admin && $user->is_admin && (int) $user->data_owner_user_id === (int) $user->id)
                                    {{ $user->invite_quota === null ? 'Tanpa batas' : $user->invite_quota . ' user' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusLabel = $statusOptions[$user->account_status] ?? strtoupper((string) $user->account_status);
                                    $statusBadge = $user->account_status === \App\Models\User::STATUS_APPROVED
                                        ? 'badge-success'
                                        : ($user->account_status === \App\Models\User::STATUS_PENDING ? 'badge-warning' : 'badge-danger');
                                @endphp
                                <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                                @if($user->banned_reason)
                                    <div class="small text-danger mt-1">Alasan: {{ $user->banned_reason }}</div>
                                @endif
                                @if($user->account_status === \App\Models\User::STATUS_PENDING)
                                    <div class="small text-muted mt-1">Menunggu approval admin</div>
                                @endif
                            </td>
                            <td>
                                @if($user->is_platform_admin)
                                    Semua Akses Platform
                                @elseif($user->is_admin)
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

                                @if(!$user->is_platform_admin)
                                    @if($user->account_status === \App\Models\User::STATUS_PENDING)
                                        <form action="{{ route('users.approve', $user) }}"
                                              method="POST"
                                              class="d-inline">
                                            @csrf
                                            <button class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Setujui
                                            </button>
                                        </form>
                                    @endif

                                    @if($user->account_status !== \App\Models\User::STATUS_BANNED)
                                        <form action="{{ route('users.ban', $user) }}"
                                              method="POST"
                                              class="d-inline ban-form">
                                            @csrf
                                            <input type="hidden" name="banned_reason" value="">
                                            <button class="btn btn-dark btn-sm">
                                                <i class="fas fa-ban"></i> Ban
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('users.unban', $user) }}"
                                              method="POST"
                                              class="d-inline">
                                            @csrf
                                            <button class="btn btn-info btn-sm">
                                                <i class="fas fa-unlock"></i> Unban
                                            </button>
                                        </form>
                                    @endif
                                @endif

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
                            <td colspan="9" class="text-center">Belum ada data user</td>
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

@push('scripts')
<script>
    document.querySelectorAll('.ban-form').forEach(form => {
        form.addEventListener('submit', function (event) {
            const reason = prompt('Alasan ban user (opsional):', '');
            if (reason === null) {
                event.preventDefault();
                return;
            }

            const input = form.querySelector('input[name="banned_reason"]');
            if (input) {
                input.value = reason;
            }
        });
    });
</script>
@endpush
