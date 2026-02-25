@extends('layouts.app')

@section('title', 'Rekening Bank')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Rekening Bank</h5>
        <a href="{{ route('bank-accounts.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah
        </a>
    </div>

    <div class="card-body">
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
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="border rounded p-3 mb-4">
            <h6 class="mb-3">Pindah Saldo Antar Rekening</h6>
            <form method="POST" action="{{ route('bank-accounts.transfer-balance') }}">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>Rekening Asal</label>
                        <select name="from_bank_account_id" class="form-control" required>
                            <option value="">-- Pilih asal --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ (string) old('from_bank_account_id') === (string) $acc->id ? 'selected' : '' }}>
                                    {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label>Rekening Tujuan</label>
                        <select name="to_bank_account_id" class="form-control" required>
                            <option value="">-- Pilih tujuan --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ (string) old('to_bank_account_id') === (string) $acc->id ? 'selected' : '' }}>
                                    {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Nominal</label>
                        <input type="number" name="amount" min="1" class="form-control" value="{{ old('amount') }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Tanggal</label>
                        <input type="date" name="transfer_date" class="form-control" value="{{ old('transfer_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-info btn-block">Pindah Saldo</button>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label>Catatan (opsional)</label>
                    <input type="text" name="note" class="form-control" value="{{ old('note') }}" placeholder="Contoh: pindah saldo dari penampung B ke A">
                </div>
            </form>
        </div>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Bank</th>
                    <th>No. Rekening</th>
                    <th>Saldo</th>
                    <th>Default</th>
                    <th width="150">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $acc)
                <tr>
                    <td>{{ $acc->name }}</td>
                    <td>{{ $acc->bank_name ?? '-' }}</td>
                    <td>{{ $acc->account_number ?? '-' }}</td>
                    <td>Rp {{ number_format($acc->balance, 0, ',', '.') }}</td>
                    <td>
                        @if($acc->is_default)
                            <span class="badge badge-success">Utama</span>
                        @else
                            <form action="{{ route('bank-accounts.update', $acc) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="is_default" value="1">
                                <input type="hidden" name="name" value="{{ $acc->name }}">
                                <input type="hidden" name="bank_name" value="{{ $acc->bank_name }}">
                                <input type="hidden" name="account_number" value="{{ $acc->account_number }}">
                                <input type="hidden" name="balance" value="{{ $acc->balance }}">
                                <button class="btn btn-outline-secondary btn-sm">Jadikan Default</button>
                            </form>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('bank-accounts.edit', $acc) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('bank-accounts.destroy', $acc) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus rekening ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center">Belum ada rekening</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
