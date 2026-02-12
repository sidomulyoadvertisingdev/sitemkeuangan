@extends('layouts.app')

@section('title', 'Detail Hutang/Piutang')

@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-1">{{ $debt->name }}</h5>
                <p class="mb-1"><strong>Jenis:</strong> {{ ucfirst($debt->type) }}</p>
                <p class="mb-1"><strong>Nominal:</strong> Rp {{ number_format($debt->amount,0,',','.') }}</p>
                <p class="mb-1"><strong>Jatuh tempo:</strong> {{ $debt->due_date ? \Carbon\Carbon::parse($debt->due_date)->format('d-m-Y') : '-' }}</p>
                <p class="mb-1"><strong>Status:</strong> {{ $debt->status }}</p>
                <p class="mb-1"><strong>Terbayar:</strong> Rp {{ number_format($paid,0,',','.') }}</p>
                <p class="mb-1"><strong>Sisa:</strong> Rp {{ number_format($remaining,0,',','.') }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Catat Cicilan</div>
            <div class="card-body">
                <form method="POST" action="{{ route('debts.installments.store', $debt) }}">
                    @csrf
                    <div class="form-group">
                        <label>Rekening</label>
                        <select name="bank_account_id" class="form-control" required>
                            <option value="">-- Pilih Rekening --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} {{ $acc->account_number ? '('.$acc->account_number.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategori {{ $debt->type === 'piutang' ? 'Pemasukan' : 'Pengeluaran' }}</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Bayar</label>
                        <input type="date" name="paid_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                    <button class="btn btn-primary btn-block">Simpan Cicilan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="card">
            <div class="card-header">Riwayat Cicilan</div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nominal</th>
                            <th>Rekening</th>
                            <th>Kategori</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($debt->installments as $item)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($item->paid_at)->format('d-m-Y') }}</td>
                                <td>Rp {{ number_format($item->amount,0,',','.') }}</td>
                                <td>{{ $item->bankAccount?->name ?? '-' }}</td>
                                <td>{{ $item->category?->name ?? '-' }}</td>
                                <td>{{ $item->note ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada cicilan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
