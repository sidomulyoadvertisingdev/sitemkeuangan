@extends('layouts.app')

@section('title', 'Edit Rekening')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('bank-accounts.update', $bankAccount) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Nama Alias</label>
                <input type="text" name="name" class="form-control" value="{{ $bankAccount->name }}" required>
            </div>

            <div class="form-group">
                <label>Bank</label>
                <input type="text" name="bank_name" class="form-control" value="{{ $bankAccount->bank_name }}">
            </div>

            <div class="form-group">
                <label>No. Rekening</label>
                <input type="text" name="account_number" class="form-control" value="{{ $bankAccount->account_number }}">
            </div>

            <div class="form-group">
                <label>Saldo</label>
                <input type="number" name="balance" class="form-control" value="{{ $bankAccount->balance }}">
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefault" {{ $bankAccount->is_default ? 'checked' : '' }}>
                <label class="form-check-label" for="isDefault">Jadikan rekening utama</label>
            </div>

            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('bank-accounts.index') }}" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
@endsection
