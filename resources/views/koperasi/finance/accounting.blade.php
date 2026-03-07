@extends('layouts.app')

@section('title', 'Accounting Koperasi')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <strong>Accounting Workspace</strong>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('koperasi.finance.report', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-file-alt mr-1"></i> Kembali ke Laporan
            </a>
        </div>
    </div>
    <div class="card-body">
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

        <form method="GET" action="{{ route('koperasi.finance.accounting') }}" class="form-row align-items-end mb-3">
            <div class="col-md-4 mb-2">
                <label for="start_date" class="mb-1">Tanggal Mulai</label>
                <input type="date" id="start_date" name="start_date" value="{{ $startDate }}" class="form-control" required>
            </div>
            <div class="col-md-4 mb-2">
                <label for="end_date" class="mb-1">Tanggal Selesai</label>
                <input type="date" id="end_date" name="end_date" value="{{ $endDate }}" class="form-control" required>
            </div>
            <div class="col-md-4 mb-2">
                <button type="submit" class="btn btn-dark">
                    <i class="fas fa-calculator mr-1"></i> Refresh Accounting
                </button>
                <a href="{{ route('koperasi.finance.accounting') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="alert alert-info mb-0">
            <strong>Mode profesional:</strong> jurnal dan neraca saldo di bawah ini dibentuk otomatis dari data simpanan, pinjaman, dan angsuran koperasi.
            Jasa/admin pinjaman ditampilkan dengan basis accrual manajemen agar posisi piutang dan hasil usaha lebih informatif.
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-lg-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#0ea5e933,#0ea5e955);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['cash_position'], 0, ',', '.') }}</h3>
                <p>Saldo Kas</p>
            </div>
            <div class="icon"><i class="fas fa-wallet"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#2563eb33,#2563eb55);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['capital_balance'], 0, ',', '.') }}</h3>
                <p>Modal Koperasi</p>
            </div>
            <div class="icon"><i class="fas fa-landmark"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#f59e0b33,#f59e0b55);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['loan_receivable'], 0, ',', '.') }}</h3>
                <p>Total Piutang Pinjaman</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-2">
        <div class="small-box" style="background: linear-gradient(135deg,#22c55e33,#22c55e55);">
            <div class="inner">
                <h3>{{ number_format($summary['wallet_count'], 0, ',', '.') }}</h3>
                <p>Dompet Accounting</p>
            </div>
            <div class="icon"><i class="fas fa-layer-group"></i></div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <strong>Tambah Dompet Baru</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('koperasi.wallets.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Nama Dompet</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Tipe</label>
                        <select name="wallet_type" class="form-control" required>
                            @foreach($walletTypeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Saldo Awal / Modal Awal</label>
                        <input type="number" name="opening_balance" min="0" step="0.01" class="form-control" value="0">
                        <small class="text-muted">Nilai ini akan masuk ke akun modal koperasi.</small>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="wallet_is_active" value="1" class="form-check-input" checked>
                        <label class="form-check-label" for="wallet_is_active">Aktif dipakai di transaksi</label>
                    </div>
                    <button class="btn btn-dark">Simpan Dompet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <strong>Saldo Per Dompet</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Dompet</th>
                                <th>Tipe</th>
                                <th class="text-right">Saldo Awal</th>
                                <th class="text-right">Masuk</th>
                                <th class="text-right">Keluar</th>
                                <th class="text-right">Saldo Akhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($walletBalances as $wallet)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $wallet['name'] }}</div>
                                        <small class="text-muted">{{ $wallet['description'] ?: '-' }}</small>
                                    </td>
                                    <td>{{ $wallet['wallet_type_label'] }}</td>
                                    <td class="text-right">Rp {{ number_format($wallet['opening_balance'], 0, ',', '.') }}</td>
                                    <td class="text-right text-success">Rp {{ number_format($wallet['inflow'], 0, ',', '.') }}</td>
                                    <td class="text-right text-danger">Rp {{ number_format($wallet['outflow'], 0, ',', '.') }}</td>
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
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <strong>Kelola Dompet Accounting</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th width="220">Nama</th>
                        <th width="170">Tipe</th>
                        <th width="170">Saldo Awal</th>
                        <th>Status</th>
                        <th>Deskripsi</th>
                        <th width="170">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($walletBalances as $wallet)
                        <tr>
                            <td>
                                <form id="wallet-update-{{ $wallet['id'] }}" method="POST" action="{{ route('koperasi.wallets.update', $wallet['id']) }}" class="d-none">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <input form="wallet-update-{{ $wallet['id'] }}" type="text" name="name" class="form-control form-control-sm mb-2" value="{{ $wallet['name'] }}" required>
                            </td>
                            <td>
                                <select form="wallet-update-{{ $wallet['id'] }}" name="wallet_type" class="form-control form-control-sm">
                                    @foreach($walletTypeOptions as $key => $label)
                                        <option value="{{ $key }}" {{ $wallet['wallet_type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input form="wallet-update-{{ $wallet['id'] }}" type="number" name="opening_balance" min="0" step="0.01" class="form-control form-control-sm" value="{{ number_format($wallet['opening_balance'], 2, '.', '') }}">
                            </td>
                            <td>
                                <input form="wallet-update-{{ $wallet['id'] }}" type="hidden" name="is_active" value="0">
                                <div class="form-check mt-1">
                                    <input form="wallet-update-{{ $wallet['id'] }}" type="checkbox" name="is_active" value="1" class="form-check-input" id="wallet_active_{{ $wallet['id'] }}" {{ $wallet['is_active'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="wallet_active_{{ $wallet['id'] }}">{{ $wallet['is_active'] ? 'Aktif' : 'Nonaktif' }}</label>
                                </div>
                            </td>
                            <td>
                                <textarea form="wallet-update-{{ $wallet['id'] }}" name="description" class="form-control form-control-sm" rows="2">{{ $wallet['description'] }}</textarea>
                            </td>
                            <td>
                                <button form="wallet-update-{{ $wallet['id'] }}" class="btn btn-primary btn-sm mb-2">
                                    <i class="fas fa-save mr-1"></i> Update
                                </button>
                                <form method="POST" action="{{ route('koperasi.wallets.destroy', $wallet['id']) }}" onsubmit="return confirm('Hapus dompet ini? Hanya bisa jika belum dipakai transaksi.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash mr-1"></i> Hapus
                                    </button>
                                </form>
                            </td>
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

<div class="row mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <strong>Chart of Accounts</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th width="70">Kode</th>
                                <th>Akun</th>
                                <th width="110">Grup</th>
                                <th width="90">Normal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($chartOfAccounts as $account)
                                <tr>
                                    <td>{{ $account['code'] }}</td>
                                    <td>
                                        <div class="font-weight-bold">{{ $account['name'] }}</div>
                                        <small class="text-muted">{{ $account['description'] }}</small>
                                    </td>
                                    <td>{{ $account['group'] }}</td>
                                    <td>{{ $account['normal_balance'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <strong>Neraca Saldo</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th width="70">Kode</th>
                                <th>Nama Akun</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trialBalance as $row)
                                <tr>
                                    <td>{{ $row['code'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-right">Rp {{ number_format($row['debit'], 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($row['credit'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-weight-bold bg-light">
                                <td colspan="2">Total</td>
                                <td class="text-right">Rp {{ number_format($trialBalanceTotals['debit'], 0, ',', '.') }}</td>
                                <td class="text-right">Rp {{ number_format($trialBalanceTotals['credit'], 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <small class="{{ abs($trialBalanceTotals['difference']) < 0.01 ? 'text-success' : 'text-danger' }}">
                    Selisih neraca saldo: Rp {{ number_format($trialBalanceTotals['difference'], 0, ',', '.') }}
                </small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Laporan Posisi Keuangan</strong>
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
                                <td colspan="2">LIABILITAS</td>
                            </tr>
                            @foreach($statement['liabilities'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="text-right">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-light font-weight-bold">
                                <td colspan="2">EKUITAS / HASIL USAHA</td>
                            </tr>
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
                <strong>Ringkasan Periodik</strong>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Pokok Dicairkan Periode</span>
                    <strong>Rp {{ number_format($summary['principal_disbursed_period'], 0, ',', '.') }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Pokok Tertagih Periode</span>
                    <strong>Rp {{ number_format($summary['principal_collected_period'], 0, ',', '.') }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Jasa/Admin Tertagih Periode</span>
                    <strong>Rp {{ number_format($summary['interest_collected_period'], 0, ',', '.') }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Denda Tertagih Periode</span>
                    <strong>Rp {{ number_format($summary['penalty_collected_period'], 0, ',', '.') }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Outstanding Jasa/Admin</span>
                    <strong>Rp {{ number_format($summary['outstanding_service'], 0, ',', '.') }}</strong>
                </div>
                <hr>
                @foreach($notes as $note)
                    <div class="mb-2 small">
                        <i class="fas fa-angle-right text-primary mr-1"></i>{{ $note }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Jurnal Otomatis Periode {{ \Carbon\Carbon::parse($startDate)->format('d-m-Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d-m-Y') }}</strong>
    </div>
    <div class="card-body p-0">
        @if($journalEntries->isEmpty())
            <div class="p-4 text-center text-muted">Belum ada jurnal pada periode ini.</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th width="100">Tanggal</th>
                            <th width="150">Referensi</th>
                            <th>Deskripsi</th>
                            <th width="90">Kode</th>
                            <th>Akun</th>
                            <th class="text-right" width="150">Debit</th>
                            <th class="text-right" width="150">Kredit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($journalEntries as $entry)
                            @foreach($entry['lines'] as $lineIndex => $line)
                                <tr>
                                    @if($lineIndex === 0)
                                        <td rowspan="{{ count($entry['lines']) }}">{{ $entry['date'] }}</td>
                                        <td rowspan="{{ count($entry['lines']) }}">{{ $entry['reference'] }}</td>
                                        <td rowspan="{{ count($entry['lines']) }}">{{ $entry['description'] }}</td>
                                    @endif
                                    <td>{{ $line['code'] }}</td>
                                    <td>{{ $line['account'] }}</td>
                                    <td class="text-right">Rp {{ number_format($line['debit'], 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($line['credit'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-light">
                                <td colspan="5" class="text-right font-weight-bold">Total Jurnal</td>
                                <td class="text-right font-weight-bold">Rp {{ number_format($entry['total'], 0, ',', '.') }}</td>
                                <td class="text-right font-weight-bold">Rp {{ number_format($entry['total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
