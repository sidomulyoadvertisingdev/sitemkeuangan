@extends('layouts.app')

@section('title', 'Detail Member Koperasi')

@section('content')
<div class="row mb-3">
    <div class="col-md-4 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#06b6d433,#06b6d455);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['total_savings'],0,',','.') }}</h3>
                <p>Total Simpanan</p>
            </div>
            <div class="icon"><i class="fas fa-piggy-bank"></i></div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#2563eb33,#2563eb55);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['total_loan_disbursed'],0,',','.') }}</h3>
                <p>Total Pinjaman Cair</p>
            </div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#f59e0b33,#f59e0b55);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['total_loan_outstanding'],0,',','.') }}</h3>
                <p>Sisa Pinjaman</p>
            </div>
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
    </div>
</div>

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

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-1">{{ $koperasi->name }}</h5>
                <p class="mb-1"><strong>No Rekening:</strong> {{ $koperasi->member_no }}</p>
                <p class="mb-1"><strong>NIK:</strong> {{ $koperasi->nik ?: '-' }}</p>
                <p class="mb-1"><strong>No HP:</strong> {{ $koperasi->phone ?: '-' }}</p>
                <p class="mb-1"><strong>Gabung:</strong> {{ optional($koperasi->join_date)->format('d-m-Y') }}</p>
                <p class="mb-1">
                    <strong>Status:</strong>
                    <span class="badge {{ $koperasi->status === 'aktif' ? 'badge-success' : 'badge-secondary' }}">
                        {{ ucfirst($koperasi->status) }}
                    </span>
                </p>
                @if($koperasi->address)
                    <p class="mb-1"><strong>Alamat:</strong> {{ $koperasi->address }}</p>
                @endif
                @if($koperasi->note)
                    <p class="mb-0"><strong>Catatan:</strong> {{ $koperasi->note }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <strong>Aksi Cepat</strong>
            </div>
            <div class="card-body">
                <div class="app-action-grid">
                    <button type="button" class="app-action-btn" data-toggle="modal" data-target="#modalSimpanan" data-bs-toggle="modal" data-bs-target="#modalSimpanan">
                        <span class="app-action-icon text-info"><i class="fas fa-piggy-bank"></i></span>
                        <span class="app-action-text">Simpanan</span>
                    </button>

                    <button type="button" class="app-action-btn" data-toggle="modal" data-target="#modalPinjaman" data-bs-toggle="modal" data-bs-target="#modalPinjaman">
                        <span class="app-action-icon text-primary"><i class="fas fa-hand-holding-usd"></i></span>
                        <span class="app-action-text">Pinjaman</span>
                    </button>

                    <button type="button" class="app-action-btn" data-toggle="modal" data-target="#modalWithdraw" data-bs-toggle="modal" data-bs-target="#modalWithdraw">
                        <span class="app-action-icon text-danger"><i class="fas fa-money-bill-wave"></i></span>
                        <span class="app-action-text">Withdraw</span>
                    </button>

                    <button type="button" class="app-action-btn" data-toggle="modal" data-target="#modalRiwayat" data-bs-toggle="modal" data-bs-target="#modalRiwayat">
                        <span class="app-action-icon text-success"><i class="fas fa-history"></i></span>
                        <span class="app-action-text">Riwayat</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Ringkasan Member</h6>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">Jumlah Pinjaman</small>
                        <strong>{{ $loans->count() }} pinjaman</strong>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">Simpanan Tercatat</small>
                        <strong>{{ $savings->count() }} transaksi</strong>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">Aksi Detail</small>
                        <strong>Buka menu Riwayat</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSimpanan" tabindex="-1" role="dialog" aria-labelledby="modalSimpananLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('koperasi.savings.store', $koperasi) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSimpananLabel">Input Simpanan</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Jenis Simpanan</label>
                        <select name="type" class="form-control" required>
                            <option value="pokok">Simpanan Pokok</option>
                            <option value="wajib">Simpanan Wajib</option>
                            <option value="sukarela">Simpanan Sukarela</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" name="amount" min="1" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan Simpanan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPinjaman" tabindex="-1" role="dialog" aria-labelledby="modalPinjamanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('koperasi.loans.store', $koperasi) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPinjamanLabel">Input Pinjaman Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>No Pinjaman</label>
                            <input type="text" name="loan_no" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Pokok Pinjaman</label>
                            <input type="number" name="principal_amount" min="1" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Bunga (%)</label>
                            <input type="number" name="interest_percent" min="0" max="100" step="0.01" class="form-control" value="0" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Biaya Admin</label>
                            <input type="number" name="admin_fee" min="0" class="form-control" value="0" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Tenor (bulan)</label>
                            <input type="number" name="tenor_months" min="1" max="240" class="form-control" value="12" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Tanggal Cair</label>
                            <input type="date" name="disbursed_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Jatuh Tempo</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Status</label>
                            <select name="status" class="form-control" required>
                                <option value="berjalan">Berjalan</option>
                                <option value="macet">Macet</option>
                                <option value="lunas">Lunas</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan Pinjaman</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalWithdraw" tabindex="-1" role="dialog" aria-labelledby="modalWithdrawLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('koperasi.withdraws.store', $koperasi) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalWithdrawLabel">Input Withdraw Simpanan</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nominal Withdraw</label>
                        <input type="number" name="amount" min="1" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Contoh: tarik simpanan sukarela"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-danger">Simpan Withdraw</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRiwayat" tabindex="-1" role="dialog" aria-labelledby="modalRiwayatLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRiwayatLabel">Riwayat Member Koperasi</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="riwayatTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-simpanan" data-toggle="tab" data-bs-toggle="tab" href="#panel-simpanan" role="tab">
                            Riwayat Simpanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-pinjaman" data-toggle="tab" data-bs-toggle="tab" href="#panel-pinjaman" role="tab">
                            Pinjaman & Angsuran
                        </a>
                    </li>
                </ul>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="panel-simpanan" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jenis</th>
                                        <th>Nominal</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($savings as $saving)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($saving->transaction_date)->format('d-m-Y') }}</td>
                                            <td>{{ ucfirst($saving->type) }}</td>
                                            <td>Rp {{ number_format($saving->amount,0,',','.') }}</td>
                                            <td>{{ $saving->note ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Belum ada transaksi simpanan.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="panel-pinjaman" role="tabpanel">
                        @forelse($loans as $loan)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex flex-wrap justify-content-between mb-2">
                                    <div>
                                        <strong>{{ $loan->loan_no }}</strong>
                                        <span class="badge ml-2 {{
                                            $loan->status === 'lunas' ? 'badge-success' :
                                            ($loan->status === 'macet' ? 'badge-danger' : 'badge-warning')
                                        }}">
                                            {{ ucfirst($loan->status) }}
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        Cair: {{ \Carbon\Carbon::parse($loan->disbursed_at)->format('d-m-Y') }}
                                        @if($loan->due_date)
                                            | Jatuh Tempo: {{ \Carbon\Carbon::parse($loan->due_date)->format('d-m-Y') }}
                                        @endif
                                    </small>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-md-4"><small>Pokok: <strong>Rp {{ number_format($loan->principal_amount,0,',','.') }}</strong></small></div>
                                    <div class="col-md-4"><small>Total Tagihan: <strong>Rp {{ number_format($loan->total_bill_value,0,',','.') }}</strong></small></div>
                                    <div class="col-md-4"><small>Sisa: <strong>Rp {{ number_format($loan->remaining_value,0,',','.') }}</strong></small></div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        Nominal angsuran otomatis:
                                        <strong>Rp {{ number_format($loan->base_installment_value,0,',','.') }}</strong>
                                        = (Pokok + Bunga + Admin) / Tenor ({{ $loan->tenor_months }} bulan).
                                        Angsuran berikutnya disarankan:
                                        <strong>Rp {{ number_format($loan->next_expected_value,0,',','.') }}</strong>.
                                    </small>
                                </div>

                                <div class="progress progress-sm mb-3">
                                    <div class="progress-bar {{ $loan->progress >= 100 ? 'bg-success' : 'bg-info' }}" style="width: {{ $loan->progress }}%"></div>
                                </div>
                                <small>Progress {{ $loan->progress }}%</small>

                                <hr>
                                <form method="POST" action="{{ route('koperasi.loans.installments.store', $loan) }}" class="mb-3">
                                    @csrf
                                    <div class="form-row">
                                        <div class="form-group col-md-2">
                                            <label>Angsuran Ke</label>
                                            <input type="number"
                                                   name="installment_no"
                                                   min="1"
                                                   class="form-control"
                                                   value="{{ $loan->next_installment_no }}">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Nominal Angsuran</label>
                                            <input type="number"
                                                   name="amount_total"
                                                   min="0.01"
                                                   step="0.01"
                                                   class="form-control"
                                                   value="{{ number_format($loan->next_expected_value, 2, '.', '') }}"
                                                   required>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label>Denda</label>
                                            <input type="number" name="amount_penalty" min="0" step="0.01" class="form-control" value="0" required>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label>Tanggal</label>
                                            <input type="date" name="paid_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Catatan</label>
                                        <textarea name="note" class="form-control" rows="2"></textarea>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm">Simpan Angsuran</button>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Ke</th>
                                                <th>Tanggal</th>
                                                <th>Nominal Wajib</th>
                                                <th>Pokok</th>
                                                <th>Bunga</th>
                                                <th>Denda</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Kurang Bayar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($loan->installments as $installment)
                                                @php
                                                    $lineTotal = (float) $installment->amount_principal
                                                        + (float) $installment->amount_interest
                                                        + (float) $installment->amount_penalty;
                                                @endphp
                                                <tr>
                                                    <td>{{ $installment->installment_no }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($installment->paid_at)->format('d-m-Y') }}</td>
                                                    <td>Rp {{ number_format($installment->expected_amount ?? 0,0,',','.') }}</td>
                                                    <td>Rp {{ number_format($installment->amount_principal,0,',','.') }}</td>
                                                    <td>Rp {{ number_format($installment->amount_interest,0,',','.') }}</td>
                                                    <td>Rp {{ number_format($installment->amount_penalty,0,',','.') }}</td>
                                                    <td>Rp {{ number_format($lineTotal,0,',','.') }}</td>
                                                    <td>
                                                        @php
                                                            $status = $installment->payment_status ?? 'sesuai';
                                                            $badge = $status === 'kurang_bayar'
                                                                ? 'badge-danger'
                                                                : ($status === 'lebih_bayar' ? 'badge-info' : 'badge-success');
                                                        @endphp
                                                        <span class="badge {{ $badge }}">
                                                            {{ str_replace('_', ' ', ucfirst($status)) }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        Rp {{ number_format((float) ($installment->shortfall_amount ?? 0),0,',','.') }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted">Belum ada angsuran.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted">Belum ada pinjaman untuk member ini.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .app-action-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .app-action-btn {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 0.9rem;
        background: #fff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 112px;
        transition: all 180ms ease;
    }

    .app-action-btn:hover {
        border-color: #93c5fd;
        box-shadow: 0 10px 18px rgba(37, 99, 235, 0.12);
        transform: translateY(-1px);
    }

    .app-action-icon {
        width: 48px;
        height: 48px;
        border-radius: 999px;
        background: #f8fafc;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 0.45rem;
    }

    .app-action-text {
        font-weight: 600;
        font-size: 0.95rem;
        color: #111827;
    }

    @media (max-width: 575.98px) {
        .app-action-btn {
            min-height: 96px;
            padding: 0.7rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.app-action-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const selector = button.getAttribute('data-bs-target') || button.getAttribute('data-target');
                if (!selector) {
                    return;
                }

                const modalEl = document.querySelector(selector);
                if (!modalEl) {
                    return;
                }

                if (window.bootstrap && window.bootstrap.Modal) {
                    const instance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                    instance.show();
                    return;
                }

                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                    window.jQuery(modalEl).modal('show');
                }
            });
        });
    });
</script>
@endpush
