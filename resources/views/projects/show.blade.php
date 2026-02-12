@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-1">{{ $project->name }}</h5>
                <p class="text-muted mb-2">{{ $project->description ?? 'Tidak ada deskripsi' }}</p>
                <p class="mb-1"><strong>Rekening:</strong> {{ $project->bankAccount?->name ?? '-' }}</p>
                <p class="mb-1"><strong>Target:</strong> Rp {{ number_format($project->target_amount,0,',','.') }}</p>
                <p class="mb-1"><strong>Dana Dialokasikan:</strong> Rp {{ number_format($allocated,0,',','.') }}</p>
                <p class="mb-1"><strong>Terpakai (net):</strong> Rp {{ number_format($netSpent,0,',','.') }}</p>
                <p class="mb-1"><strong>Sisa:</strong> Rp {{ number_format($balance,0,',','.') }}</p>
                <div class="progress mt-2" style="height: 10px;">
                    <div class="progress-bar" style="width: {{ $progress }}%"></div>
                </div>
                <small class="text-muted">Progress terhadap target: {{ $progress }}%</small>
                <form action="{{ route('projects.destroy', $project) }}" method="POST" class="mt-3" onsubmit="return confirm('Hapus proyek beserta semua transaksinya?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-trash"></i> Hapus Proyek
                    </button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Alokasi / Top-up</div>
            <div class="card-body">
                <form method="POST" action="{{ route('projects.allocate', $project) }}">
                    @csrf
                    <input type="hidden" name="type" value="allocation">
                    <div class="form-group">
                        <label>Rekening</label>
                        <select name="bank_account_id" class="form-control" required>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                    <button class="btn btn-primary btn-block">Simpan Alokasi</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Tambah Pengeluaran</div>
            <div class="card-body">
                <form method="POST" action="{{ route('projects.expenses.store', $project) }}">
                    @csrf
                    <div class="form-group">
                        <label>Rekening</label>
                        <select name="bank_account_id" class="form-control" required>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategori Pengeluaran</label>
                        <select name="category_id" class="form-control">
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
                        <label>Tanggal</label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                    <button class="btn btn-danger btn-block">Catat Pengeluaran</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-header">Log Transaksi Proyek</div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Kategori</th>
                            <th>Nominal</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->transactions as $tx)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($tx->date)->format('d-m-Y') }}</td>
                                <td><span class="badge badge-{{ $tx->type === 'expense' ? 'danger' : 'primary' }}">{{ $tx->type }}</span></td>
                                <td>{{ $tx->category?->name ?? '-' }}</td>
                                <td>Rp {{ number_format($tx->amount,0,',','.') }}</td>
                                <td>{{ $tx->note ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada transaksi</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
