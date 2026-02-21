@extends('layouts.app')

@section('title', 'Tambah User')

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

        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <div class="form-group">
                <label>Nama</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>

            <div class="form-group">
                <label>Nama Perkumpulan</label>
                <input type="text"
                       name="organization_name"
                       class="form-control"
                       value="{{ old('organization_name', !$canChooseAccessOrganization ? ($currentAccessOrganization?->organization_name ?? '') : '') }}"
                       data-default-org="{{ !$canChooseAccessOrganization ? ($currentAccessOrganization?->organization_name ?? '') : '' }}"
                       required>
            </div>

            <div class="form-group" id="accessOrgBox">
                <label>Akses Data Perkumpulan</label>
                @if($canChooseAccessOrganization)
                    <select name="data_owner_user_id" id="data_owner_user_id" class="form-control">
                        <option value="">-- Pilih Perkumpulan --</option>
                        @foreach($accessOrganizations as $organization)
                            <option value="{{ $organization->id }}"
                                    data-org="{{ $organization->organization_name }}"
                                    {{ (string) old('data_owner_user_id') === (string) $organization->id ? 'selected' : '' }}>
                                {{ $organization->organization_name }} (Owner: {{ $organization->name }})
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Khusus user non-admin: hanya bisa akses data perkumpulan yang dipilih.</small>
                @else
                    <input type="hidden"
                           name="data_owner_user_id"
                           value="{{ old('data_owner_user_id', $currentAccessOrganization?->id) }}">
                    <input type="text"
                           class="form-control"
                           value="{{ $currentAccessOrganization?->organization_name }} (Owner: {{ $currentAccessOrganization?->name }})"
                           readonly>
                    <small class="text-muted">Owner hanya bisa menambahkan anggota ke perkumpulannya sendiri.</small>
                @endif
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>

            <div class="form-group form-check">
                <input type="hidden" name="is_admin" value="0">
                <input type="checkbox" name="is_admin" id="is_admin" value="1" class="form-check-input" {{ old('is_admin') ? 'checked' : '' }}>
                <label class="form-check-label" for="is_admin">Jadikan Admin (akses penuh)</label>
            </div>

            @if($canManageInviteQuota)
                <div class="form-group" id="inviteQuotaBox">
                    <label>Batas Invite User Perkumpulan</label>
                    <input type="number"
                           name="invite_quota"
                           id="invite_quota"
                           class="form-control"
                           min="0"
                           step="1"
                           value="{{ old('invite_quota') }}"
                           placeholder="Kosongkan jika tanpa batas">
                    <small class="text-muted">Diatur oleh platform admin. Berlaku untuk jumlah user anggota perkumpulan.</small>
                </div>
            @endif

            <div class="form-group" id="permissionBox">
                <label>Hak Akses User</label>
                @foreach($permissionOptions as $key => $label)
                    <div class="form-check">
                        <input type="checkbox"
                               name="permissions[]"
                               id="perm_{{ $loop->index }}"
                               value="{{ $key }}"
                               class="form-check-input"
                               {{ in_array($key, old('permissions', [])) ? 'checked' : '' }}>
                        <label class="form-check-label" for="perm_{{ $loop->index }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>

            <button class="btn btn-primary">Simpan</button>
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
        const accessOrgBox = document.getElementById('accessOrgBox');
        const accessOrgSelect = document.getElementById('data_owner_user_id');
        const organizationInput = document.querySelector('input[name="organization_name"]');
        const inviteQuotaBox = document.getElementById('inviteQuotaBox');
        const inviteQuotaInput = document.getElementById('invite_quota');
        const canChooseAccessOrganization = @json((bool) $canChooseAccessOrganization);
        const canManageInviteQuota = @json((bool) $canManageInviteQuota);

        function togglePermissions() {
            if (!adminCheckbox || !permissionBox || !accessOrgBox || !organizationInput) return;

            const isAdmin = adminCheckbox.checked;
            permissionBox.style.display = isAdmin ? 'none' : '';
            accessOrgBox.style.display = isAdmin ? 'none' : '';
            if (accessOrgSelect) {
                accessOrgSelect.required = !isAdmin;
            }

            if (isAdmin) {
                organizationInput.readOnly = !canChooseAccessOrganization;
            } else {
                organizationInput.readOnly = true;
                if (accessOrgSelect) {
                    const selected = accessOrgSelect.options[accessOrgSelect.selectedIndex];
                    organizationInput.value = selected && selected.dataset.org ? selected.dataset.org : '';
                } else if (organizationInput.dataset.defaultOrg) {
                    organizationInput.value = organizationInput.dataset.defaultOrg;
                }
            }

            if (inviteQuotaBox && inviteQuotaInput) {
                const showInviteQuota = canManageInviteQuota && isAdmin;
                inviteQuotaBox.style.display = showInviteQuota ? '' : 'none';
                inviteQuotaInput.disabled = !showInviteQuota;
                if (!showInviteQuota) {
                    inviteQuotaInput.value = '';
                }
            }
        }

        accessOrgSelect?.addEventListener('change', togglePermissions);
        adminCheckbox?.addEventListener('change', togglePermissions);
        togglePermissions();
    })();
</script>
@endpush
