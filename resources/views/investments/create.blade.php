@extends('layouts.app')

@section('title','Alokasi Investasi')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('investments.store') }}">
            @csrf

            <div class="form-group">
                <label>Aset</label>
                <select name="investment_asset_id" class="form-control" required>
                    <option value="">-- Pilih Aset --</option>
                    @foreach($assets as $asset)
                        <option value="{{ $asset->id }}">
                            {{ $asset->name }} ({{ strtoupper($asset->category) }})
                            {{ $asset->symbol ? '- '.$asset->symbol : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Rekening Sumber Dana</label>
                <select name="bank_account_id" class="form-control" required>
                    <option value="">-- Pilih Rekening --</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }} {{ $acc->account_number ? '('.$acc->account_number.')' : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Nominal (IDR)</label>
                <input type="number" name="amount_fiat" class="form-control" required min="1" step="0.01">
                <small class="text-muted">Dana ini akan mengurangi saldo rekening (bisa minus).</small>
            </div>

            <div class="form-group">
                <label>Tanggal Eksekusi</label>
                <input type="date" name="executed_at" class="form-control" value="{{ date('Y-m-d') }}">
            </div>

            <div class="form-group">
                <label>Catatan</label>
                <textarea name="note" class="form-control" rows="3"></textarea>
            </div>

            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('investments.index') }}" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
@endsection
