@extends('layouts.app')

@section('title', 'Laporan Lengkap Keuangan')

@section('content')
<div class="card mb-3">
    <div class="card-header">
        <strong>Filter Periode Laporan</strong>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="GET" action="{{ route('reports.index') }}" class="form-row align-items-end">
            <div class="col-md-4 mb-2">
                <label for="start_date" class="mb-1">Tanggal Mulai</label>
                <input type="date"
                       id="start_date"
                       name="start_date"
                       value="{{ $startDate }}"
                       class="form-control"
                       required>
            </div>
            <div class="col-md-4 mb-2">
                <label for="end_date" class="mb-1">Tanggal Selesai</label>
                <input type="date"
                       id="end_date"
                       name="end_date"
                       value="{{ $endDate }}"
                       class="form-control"
                       required>
            </div>
            <div class="col-md-4 mb-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-file-invoice mr-1"></i> Generate Laporan
                </button>
                <a href="{{ route('reports.export.pdf', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                   class="btn btn-danger">
                    <i class="fas fa-file-pdf mr-1"></i> Export PDF
                </a>
                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#22c55e33,#22c55e55);">
            <div class="inner">
                <h3>Rp {{ number_format($totalIncome, 0, ',', '.') }}</h3>
                <p>Total Pemasukan</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-down"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#ef444433,#ef444455);">
            <div class="inner">
                <h3>Rp {{ number_format($totalExpense, 0, ',', '.') }}</h3>
                <p>Total Pengeluaran</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-up"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#0ea5e933,#0ea5e955);">
            <div class="inner">
                <h3>Rp {{ number_format($netBalance, 0, ',', '.') }}</h3>
                <p>Saldo Bersih</p>
            </div>
            <div class="icon"><i class="fas fa-wallet"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#f59e0b33,#f59e0b55);">
            <div class="inner">
                <h3>{{ $transactions->count() }}</h3>
                <p>Jumlah Transaksi</p>
            </div>
            <div class="icon"><i class="fas fa-list"></i></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Sumber Pemasukan</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Sumber</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incomeBySource as $label => $amount)
                                @php
                                    $percent = $totalIncome > 0 ? round(($amount / $totalIncome) * 100, 2) : 0;
                                @endphp
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                                    <td class="text-right">{{ $percent }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Sumber Pengeluaran</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Sumber</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenseBySource as $label => $amount)
                                @php
                                    $percent = $totalExpense > 0 ? round(($amount / $totalExpense) * 100, 2) : 0;
                                @endphp
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                                    <td class="text-right">{{ $percent }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Pemasukan Berdasarkan Kategori</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incomeByCategory as $label => $amount)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Pengeluaran Berdasarkan Kategori</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenseByCategory as $label => $amount)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Sumber Pemasukan dari Rekening</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Rekening</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incomeByBankAccount as $label => $amount)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Sumber Pengeluaran dari Rekening</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Rekening</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenseByBankAccount as $label => $amount)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Pengeluaran Untuk Apa Saja</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Keperluan / Kategori</th>
                        <th class="text-right">Nominal</th>
                        <th class="text-right">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenseUsage as $item)
                        <tr>
                            <td>{{ $item['label'] }}</td>
                            <td class="text-right">Rp {{ number_format($item['amount'], 0, ',', '.') }}</td>
                            <td class="text-right">{{ $item['percent'] }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Detail Transaksi Periode {{ \Carbon\Carbon::parse($startDate)->format('d-m-Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d-m-Y') }}</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped mb-0">
                <thead>
                    <tr>
                        <th width="110">Tanggal</th>
                        <th width="110">Jenis</th>
                        <th>Sumber</th>
                        <th>Kategori</th>
                        <th>Rekening</th>
                        <th>Proyek</th>
                        <th>Catatan</th>
                        <th class="text-right" width="150">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($transaction->date)->format('d-m-Y') }}</td>
                            <td>
                                <span class="badge {{ $transaction->type === 'income' ? 'badge-success' : 'badge-danger' }}">
                                    {{ $transaction->type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}
                                </span>
                            </td>
                            <td>{{ $transaction->source_label }}</td>
                            <td>{{ $transaction->category?->name ?? '-' }}</td>
                            <td>{{ $transaction->bankAccount?->name ?? '-' }}</td>
                            <td>{{ $transaction->project?->name ?? '-' }}</td>
                            <td>{{ $transaction->note ?? '-' }}</td>
                            <td class="text-right">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">Tidak ada transaksi pada periode ini</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
