@extends('layouts.app')

@section('title', 'Transfer & Request Pembayaran')

@section('content')
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 pl-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Transfer Langsung Antar Rekening</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('transfers.direct.store') }}">
                    @csrf

                    <div class="form-group">
                        <label>Rekening Asal (Organisasi Anda)</label>
                        <select name="sender_bank_account_id" class="form-control" required>
                            <option value="">-- Pilih rekening asal --</option>
                            @foreach($ownAccounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('sender_bank_account_id') === (string) $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}{{ $account->account_number ? ' (' . $account->account_number . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Organisasi Tujuan</label>
                        <select name="receiver_user_id"
                                id="directReceiverOrganization"
                                class="form-control"
                                data-old="{{ old('receiver_user_id') }}"
                                required>
                            <option value="">-- Pilih organisasi tujuan --</option>
                            @foreach($organizations as $organization)
                                <option value="{{ $organization->id }}" {{ (string) old('receiver_user_id') === (string) $organization->id ? 'selected' : '' }}>
                                    {{ $organization->organization_name ?: $organization->name }}
                                    @if($organization->organization_name && strcasecmp($organization->organization_name, $organization->name) !== 0)
                                        - {{ $organization->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Rekening Tujuan</label>
                        <select name="receiver_bank_account_id"
                                id="directReceiverAccount"
                                class="form-control"
                                data-old="{{ old('receiver_bank_account_id') }}"
                                required>
                            <option value="">-- Pilih rekening tujuan --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" name="amount" class="form-control" min="1" value="{{ old('amount') }}" required>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Transfer</label>
                        <input type="date" name="transfer_date" class="form-control" value="{{ old('transfer_date', now()->toDateString()) }}" required>
                    </div>

                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>

                    <button class="btn btn-primary">Proses Transfer</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Request Pembayaran Antar Organisasi</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('transfers.requests.store') }}">
                    @csrf

                    <div class="form-group">
                        <label>Ditujukan ke Organisasi (Yang Membayar)</label>
                        <select name="payer_user_id" class="form-control" required>
                            <option value="">-- Pilih organisasi --</option>
                            @foreach($organizations as $organization)
                                @if((int) $organization->id !== (int) $tenantId)
                                    <option value="{{ $organization->id }}" {{ (string) old('payer_user_id') === (string) $organization->id ? 'selected' : '' }}>
                                        {{ $organization->organization_name ?: $organization->name }}
                                        @if($organization->organization_name && strcasecmp($organization->organization_name, $organization->name) !== 0)
                                            - {{ $organization->name }}
                                        @endif
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Rekening Penampung (Milik Organisasi Anda)</label>
                        <select name="receiver_bank_account_id" class="form-control" required>
                            <option value="">-- Pilih rekening penerima --</option>
                            @foreach($ownAccounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('receiver_bank_account_id') === (string) $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}{{ $account->account_number ? ' (' . $account->account_number . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nominal Request</label>
                        <input type="number" name="amount" class="form-control" min="1" value="{{ old('amount') }}" required>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Diminta Bayar</label>
                        <input type="date" name="transfer_date" class="form-control" value="{{ old('transfer_date', now()->toDateString()) }}" required>
                    </div>

                    <div class="form-group">
                        <label>Catatan Request</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>

                    <button class="btn btn-success">Kirim Request</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Request Pembayaran Masuk (Perlu Anda Proses)</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Organisasi Peminta</th>
                    <th>Rekening Tujuan Mereka</th>
                    <th>Nominal</th>
                    <th>Catatan</th>
                    <th width="340">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incomingRequests as $requestItem)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($requestItem->transfer_date)->format('d-m-Y') }}</td>
                        <td>
                            {{ $requestItem->receiverOrganization?->organization_name ?: $requestItem->receiverOrganization?->name ?: '-' }}
                            @if($requestItem->receiverOrganization && $requestItem->receiverOrganization->organization_name && strcasecmp($requestItem->receiverOrganization->organization_name, $requestItem->receiverOrganization->name) !== 0)
                                <br><small class="text-muted">{{ $requestItem->receiverOrganization->name }}</small>
                            @endif
                        </td>
                        <td>
                            {{ $requestItem->receiverBankAccount?->name ?? '-' }}
                            @if($requestItem->receiverBankAccount?->account_number)
                                <br><small class="text-muted">{{ $requestItem->receiverBankAccount->account_number }}</small>
                            @endif
                        </td>
                        <td>Rp {{ number_format($requestItem->amount, 0, ',', '.') }}</td>
                        <td>{{ $requestItem->note ?: '-' }}</td>
                        <td>
                            <form method="POST" action="{{ route('transfers.requests.pay', $requestItem) }}" class="mb-2">
                                @csrf
                                <div class="form-row">
                                    <div class="col-md-5 mb-1">
                                        <select name="sender_bank_account_id" class="form-control form-control-sm" required>
                                            <option value="">Rekening bayar</option>
                                            @foreach($ownAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-1">
                                        <input type="date" name="transfer_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                                    </div>
                                    <div class="col-md-3 mb-1">
                                        <button type="submit" class="btn btn-primary btn-sm btn-block">Bayar</button>
                                    </div>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('transfers.requests.reject', $requestItem) }}">
                                @csrf
                                <div class="form-row">
                                    <div class="col-md-8 mb-1">
                                        <input type="text"
                                               name="rejected_reason"
                                               class="form-control form-control-sm"
                                               placeholder="Alasan penolakan (opsional)">
                                    </div>
                                    <div class="col-md-4 mb-1">
                                        <button type="submit" class="btn btn-outline-danger btn-sm btn-block">Tolak</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">Tidak ada request pembayaran masuk.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Request Pembayaran Keluar (Menunggu Persetujuan)</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Ditagih ke</th>
                    <th>Rekening Penampung Anda</th>
                    <th>Nominal</th>
                    <th>Catatan</th>
                    <th width="120">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($outgoingRequests as $requestItem)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($requestItem->transfer_date)->format('d-m-Y') }}</td>
                        <td>
                            {{ $requestItem->senderOrganization?->organization_name ?: $requestItem->senderOrganization?->name ?: '-' }}
                            @if($requestItem->senderOrganization && $requestItem->senderOrganization->organization_name && strcasecmp($requestItem->senderOrganization->organization_name, $requestItem->senderOrganization->name) !== 0)
                                <br><small class="text-muted">{{ $requestItem->senderOrganization->name }}</small>
                            @endif
                        </td>
                        <td>{{ $requestItem->receiverBankAccount?->name ?? '-' }}</td>
                        <td>Rp {{ number_format($requestItem->amount, 0, ',', '.') }}</td>
                        <td>{{ $requestItem->note ?: '-' }}</td>
                        <td>
                            <form method="POST" action="{{ route('transfers.requests.cancel', $requestItem) }}">
                                @csrf
                                <button type="submit"
                                        class="btn btn-outline-secondary btn-sm"
                                        onclick="return confirm('Batalkan request pembayaran ini?')">
                                    Batalkan
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">Tidak ada request pembayaran keluar yang menunggu.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Riwayat Transfer & Request</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Status</th>
                    <th>Dari</th>
                    <th>Ke</th>
                    <th>Nominal</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $item)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($item->transfer_date)->format('d-m-Y') }}</td>
                        <td>
                            @if($item->kind === \App\Models\AccountTransfer::KIND_DIRECT_TRANSFER)
                                <span class="badge badge-info">Transfer Langsung</span>
                            @else
                                <span class="badge badge-secondary">Request Pembayaran</span>
                            @endif
                        </td>
                        <td>
                            @if($item->status === \App\Models\AccountTransfer::STATUS_COMPLETED)
                                <span class="badge badge-success">Selesai</span>
                            @elseif($item->status === \App\Models\AccountTransfer::STATUS_PENDING)
                                <span class="badge badge-warning">Pending</span>
                            @elseif($item->status === \App\Models\AccountTransfer::STATUS_REJECTED)
                                <span class="badge badge-danger">Ditolak</span>
                            @else
                                <span class="badge badge-dark">Dibatalkan</span>
                            @endif
                        </td>
                        <td>
                            {{ $item->senderOrganization?->organization_name ?: $item->senderOrganization?->name ?: '-' }}
                            @if($item->senderBankAccount)
                                <br><small class="text-muted">[{{ $item->senderBankAccount->name }}]</small>
                            @endif
                        </td>
                        <td>
                            {{ $item->receiverOrganization?->organization_name ?: $item->receiverOrganization?->name ?: '-' }}
                            @if($item->receiverBankAccount)
                                <br><small class="text-muted">[{{ $item->receiverBankAccount->name }}]</small>
                            @endif
                        </td>
                        <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                        <td>
                            {{ $item->note ?: '-' }}
                            @if($item->rejected_reason)
                                <br><small class="text-danger">Alasan tolak: {{ $item->rejected_reason }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada data transfer / request.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const accountGroups = @json($accountsByOrganization);
    const receiverOrganizationInput = document.getElementById('directReceiverOrganization');
    const receiverAccountInput = document.getElementById('directReceiverAccount');

    function renderReceiverAccounts(organizationId, selectedId = '') {
        receiverAccountInput.innerHTML = '<option value="">-- Pilih rekening tujuan --</option>';

        if (!organizationId || !accountGroups[organizationId]) {
            return;
        }

        accountGroups[organizationId].forEach((account) => {
            const option = document.createElement('option');
            option.value = account.id;
            option.text = account.name + (account.account_number ? ` (${account.account_number})` : '');
            if (String(option.value) === String(selectedId)) {
                option.selected = true;
            }
            receiverAccountInput.appendChild(option);
        });
    }

    receiverOrganizationInput?.addEventListener('change', function () {
        renderReceiverAccounts(this.value);
    });

    document.addEventListener('DOMContentLoaded', () => {
        const oldOrganization = receiverOrganizationInput?.dataset.old || receiverOrganizationInput?.value || '';
        const oldAccount = receiverAccountInput?.dataset.old || '';

        if (oldOrganization && receiverOrganizationInput) {
            receiverOrganizationInput.value = oldOrganization;
        }

        renderReceiverAccounts(oldOrganization, oldAccount);
    });
</script>
@endpush
