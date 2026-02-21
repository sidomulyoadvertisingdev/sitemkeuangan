@extends('layouts.app')

@section('title', 'Detail Iuran Anggota')

@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-1">{{ $iuran->name }}</h5>
                <p class="mb-1">
                    <strong>Status:</strong>
                    <span class="badge {{ $iuran->status === 'lunas' ? 'badge-success' : 'badge-warning' }}">
                        {{ $iuran->status === 'lunas' ? 'Lunas' : 'Aktif' }}
                    </span>
                </p>
                <p class="mb-1"><strong>Target:</strong> Rp {{ number_format($iuran->target_amount,0,',','.') }}</p>
                <p class="mb-1"><strong>Periode Target:</strong> {{ $iuran->target_period }}</p>
                <p class="mb-1"><strong>Terbayar:</strong> Rp {{ number_format($paid,0,',','.') }}</p>
                <p class="mb-2"><strong>Sisa:</strong> Rp {{ number_format($remaining,0,',','.') }}</p>

                <div class="progress progress-sm mb-1">
                    <div class="progress-bar {{ $progress >= 100 ? 'bg-success' : 'bg-info' }}" style="width: {{ $progress }}%"></div>
                </div>
                <small>Progress {{ $progress }}%</small>

                @if($iuran->note)
                    <hr>
                    <p class="mb-0"><strong>Catatan:</strong> {{ $iuran->note }}</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">Catat Cicilan Iuran</div>
            <div class="card-body">
                <form method="POST" action="{{ route('iuran.installments.store', $iuran) }}">
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
                        <label>Kategori Pemasukan</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nominal Cicilan</label>
                        <input type="number" name="amount" class="form-control" min="1" required>
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

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
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
                        @forelse($iuran->installments as $item)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($item->paid_at)->format('d-m-Y') }}</td>
                                <td>Rp {{ number_format($item->amount,0,',','.') }}</td>
                                <td>{{ $item->bankAccount?->name ?? '-' }}</td>
                                <td>{{ $item->category?->name ?? '-' }}</td>
                                <td>{{ $item->note ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada cicilan iuran</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
