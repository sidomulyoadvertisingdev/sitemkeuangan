@extends('layouts.app')

@section('title', 'Laporan Keuangan Koperasi')

@section('content')
<div class="card mb-4">
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

        <form method="GET" action="{{ route('koperasi.finance.report') }}" class="form-row align-items-end">
            <div class="col-md-4 mb-2">
                <label for="start_date" class="mb-1">Tanggal Mulai</label>
                <input type="date" id="start_date" name="start_date" value="{{ $startDate }}" class="form-control" required>
            </div>
            <div class="col-md-4 mb-2">
                <label for="end_date" class="mb-1">Tanggal Selesai</label>
                <input type="date" id="end_date" name="end_date" value="{{ $endDate }}" class="form-control" required>
            </div>
            <div class="col-md-4 mb-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-chart-bar mr-1"></i> Generate Laporan
                </button>
                <a href="{{ route('koperasi.finance.report') }}" class="btn btn-outline-secondary">Reset</a>
                <a href="{{ route('koperasi.finance.accounting', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-outline-dark">
                    <i class="fas fa-calculator mr-1"></i> Buka Accounting
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-lg-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#22c55e33,#22c55e55);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['cash_in_period'], 0, ',', '.') }}</h3>
                <p>Kas Masuk Periode</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-down"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#ef444433,#ef444455);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['cash_out_period'], 0, ',', '.') }}</h3>
                <p>Kas Keluar Periode</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-up"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#0ea5e933,#0ea5e955);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['cash_position'], 0, ',', '.') }}</h3>
                <p>Posisi Kas Koperasi</p>
            </div>
            <div class="icon"><i class="fas fa-wallet"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#2563eb33,#2563eb55);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['loan_receivable'], 0, ',', '.') }}</h3>
                <p>Piutang Pinjaman Berjalan</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <strong>Ringkasan Arus Kas Periode</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Komponen</th>
                                <th>Keterangan</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cashflowBreakdown as $row)
                                <tr>
                                    <td>
                                        <span class="badge {{ $row['direction'] === 'in' ? 'badge-success' : 'badge-danger' }}">
                                            {{ $row['direction'] === 'in' ? 'Kas Masuk' : 'Kas Keluar' }}
                                        </span>
                                        {{ $row['label'] }}
                                    </td>
                                    <td>{{ $row['description'] }}</td>
                                    <td class="text-right">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Belum ada arus kas pada periode ini.</td>
                                </tr>
                            @endforelse
                            <tr class="font-weight-bold bg-light">
                                <td colspan="2">Net Cashflow Periode</td>
                                <td class="text-right">Rp {{ number_format($summary['net_cashflow_period'], 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <strong>Snapshot Kinerja</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small">Member</div>
                    <div class="h5 mb-0">{{ number_format($summary['members'], 0, ',', '.') }} total / {{ number_format($summary['active_members'], 0, ',', '.') }} aktif</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Pendapatan Jasa Dibukukan</div>
                    <div class="h5 mb-0">Rp {{ number_format($summary['service_billed_period'], 0, ',', '.') }}</div>
                    <small class="text-muted">Admin fee periode: Rp {{ number_format($summary['admin_billed_period'], 0, ',', '.') }}</small>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Denda Tertagih</div>
                    <div class="h5 mb-0">Rp {{ number_format($summary['penalty_collected_period'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-muted small">Shortfall Angsuran Outstanding</div>
                    <div class="h5 mb-0">Rp {{ number_format($summary['shortfall_outstanding'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Mutasi Simpanan Periode</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th class="text-right">Nominal Bersih</th>
                                <th class="text-right">Komposisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($savingsByType as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="text-right">Rp {{ number_format($row['net_amount'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ $row['percentage'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Belum ada mutasi simpanan pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Portofolio Pinjaman</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-right">Jumlah Pinjaman</th>
                                <th class="text-right">Pokok Dicairkan</th>
                                <th class="text-right">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loanStatusSummary as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="text-right">{{ number_format($row['count'], 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($row['principal_amount'], 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($row['outstanding_amount'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Belum ada portofolio pinjaman.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Laporan Posisi Keuangan Ringkas</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody>
                            <tr class="bg-light font-weight-bold">
                                <td colspan="2">ASET</td>
                            </tr>
                            @foreach($statement['assets'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="text-right">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-weight-bold">
                                <td>Total Aset</td>
                                <td class="text-right">Rp {{ number_format($statement['total_assets'], 0, ',', '.') }}</td>
                            </tr>
                            <tr class="bg-light font-weight-bold">
                                <td colspan="2">LIABILITAS + EKUITAS</td>
                            </tr>
                            @foreach($statement['liabilities'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="text-right">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            @foreach($statement['equity'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="text-right">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-weight-bold">
                                <td>Total Liabilitas + Ekuitas</td>
                                <td class="text-right">Rp {{ number_format($statement['total_liabilities_equity'], 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Catatan Laporan</strong>
            </div>
            <div class="card-body">
                @foreach($notes as $note)
                    <div class="mb-2">
                        <i class="fas fa-check-circle text-primary mr-1"></i>{{ $note }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <strong>Ringkasan Saldo Dompet</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Dompet</th>
                        <th>Tipe</th>
                        <th class="text-right">Saldo Awal</th>
                        <th class="text-right">Kas Masuk</th>
                        <th class="text-right">Kas Keluar</th>
                        <th class="text-right">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($walletBalances as $wallet)
                        <tr>
                            <td>{{ $wallet['name'] }}</td>
                            <td>{{ $wallet['wallet_type_label'] }}</td>
                            <td class="text-right">Rp {{ number_format($wallet['opening_balance'], 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($wallet['inflow'], 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($wallet['outflow'], 0, ',', '.') }}</td>
                            <td class="text-right font-weight-bold">Rp {{ number_format($wallet['closing_balance'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada dompet accounting.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Aktivitas Keuangan Periode {{ \Carbon\Carbon::parse($startDate)->format('d-m-Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d-m-Y') }}</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped mb-0">
                <thead>
                    <tr>
                        <th width="110">Tanggal</th>
                        <th width="140">Referensi</th>
                        <th width="140">No Rekening</th>
                        <th>Nama Member</th>
                        <th>Aktivitas</th>
                        <th>Dompet</th>
                        <th width="120">Arah Kas</th>
                        <th class="text-right" width="140">Nominal</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activityRows as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['reference'] }}</td>
                            <td>{{ $row['member_no'] }}</td>
                            <td>{{ $row['member_name'] }}</td>
                            <td>{{ $row['activity'] }}</td>
                            <td>{{ $row['wallet_name'] }}</td>
                            <td>
                                <span class="badge {{ $row['cash_direction'] === 'Kas Masuk' ? 'badge-success' : 'badge-danger' }}">
                                    {{ $row['cash_direction'] }}
                                </span>
                            </td>
                            <td class="text-right">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                            <td>{{ $row['note'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">Tidak ada aktivitas pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
