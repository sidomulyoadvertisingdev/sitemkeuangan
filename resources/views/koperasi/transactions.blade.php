@extends('layouts.app')

@section('title', 'Transaction')

@section('content')
@php
    $menuRoute = $menuKey === 'bagi_hasil' ? 'bagi-hasil' : $menuKey;
    $colspan = match ($menuKey) {
        'simpan' => 6,
        'pinjam' => 8,
        'withdraw' => 6,
        'angsuran' => 6,
        'bagi_hasil' => 6,
        default => 6,
    };

    $addAction = match ($menuKey) {
        'simpan' => ['target' => '#modalTambahSimpan', 'label' => 'Tambah Simpanan', 'class' => 'btn-primary', 'icon' => 'fas fa-plus-circle'],
        'pinjam' => ['target' => '#modalTambahPinjam', 'label' => 'Tambah Pinjaman', 'class' => 'btn-primary', 'icon' => 'fas fa-plus-circle'],
        'withdraw' => ['target' => '#modalTambahWithdraw', 'label' => 'Tambah Withdraw', 'class' => 'btn-danger', 'icon' => 'fas fa-minus-circle'],
        'angsuran' => ['target' => '#modalTambahAngsuran', 'label' => 'Tambah Angsuran', 'class' => 'btn-primary', 'icon' => 'fas fa-plus-circle'],
        default => null,
    };

    $memberReferenceMap = $memberReferences
        ->keyBy('member_no')
        ->map(function ($member) {
            return [
                'account_no' => (string) $member->member_no,
                'name' => (string) $member->name,
                'nik' => (string) ($member->nik ?? '-'),
                'phone' => (string) ($member->phone ?? '-'),
                'status' => ucfirst((string) ($member->status ?? '-')),
            ];
        })
        ->all();
@endphp

<datalist id="memberReferenceList">
    @foreach($memberReferences as $member)
        <option value="{{ $member->member_no }}">{{ $member->name }} @if($member->nik) | NIK: {{ $member->nik }} @endif</option>
    @endforeach
</datalist>

<datalist id="loanReferenceList">
    @foreach($loanReferences as $loan)
        <option value="{{ $loan->loan_no }}">{{ $loan->member_name }} | Rek: {{ $loan->member_no }} | {{ ucfirst($loan->status) }}</option>
    @endforeach
</datalist>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-start justify-content-between">
        <div>
            <h3 class="mb-1 font-weight-bold">Transaction</h3>
            <div class="text-muted">{{ $menuLabel }}</div>
        </div>
        <div class="d-flex flex-wrap align-items-center mt-2 mt-md-0">
            <div class="btn-group btn-group-sm mr-2 mb-2 mb-md-0">
                <a href="{{ route('koperasi.transactions', ['menu' => 'simpan']) }}" class="btn {{ $menuRoute === 'simpan' ? 'btn-primary' : 'btn-outline-primary' }}">Simpan</a>
                <a href="{{ route('koperasi.transactions', ['menu' => 'pinjam']) }}" class="btn {{ $menuRoute === 'pinjam' ? 'btn-primary' : 'btn-outline-primary' }}">Pinjam</a>
                <a href="{{ route('koperasi.transactions', ['menu' => 'withdraw']) }}" class="btn {{ $menuRoute === 'withdraw' ? 'btn-primary' : 'btn-outline-primary' }}">Withdraw</a>
                <a href="{{ route('koperasi.transactions', ['menu' => 'angsuran']) }}" class="btn {{ $menuRoute === 'angsuran' ? 'btn-primary' : 'btn-outline-primary' }}">Angsuran</a>
                <a href="{{ route('koperasi.transactions', ['menu' => 'bagi-hasil']) }}" class="btn {{ $menuRoute === 'bagi-hasil' ? 'btn-primary' : 'btn-outline-primary' }}">Bagi Hasil</a>
            </div>

            @if($addAction)
                <button type="button"
                        class="btn btn-sm {{ $addAction['class'] }}"
                        data-toggle="modal"
                        data-target="{{ $addAction['target'] }}"
                        data-bs-toggle="modal"
                        data-bs-target="{{ $addAction['target'] }}">
                    <i class="{{ $addAction['icon'] }} mr-1"></i>{{ $addAction['label'] }}
                </button>
            @endif
        </div>
    </div>

    <div class="card-body p-0">
        <div class="px-3 py-3 border-bottom">
            @if(session('success'))
                <div class="alert alert-success py-2">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger py-2">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="GET" action="{{ route('koperasi.transactions', ['menu' => $menuRoute]) }}">
                <div class="input-group input-group-sm" style="max-width: 460px;">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Cari member / nomor transaksi / catatan..."
                           value="{{ $q }}">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <a href="{{ route('koperasi.transactions', ['menu' => $menuRoute]) }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
            <div class="small text-muted mt-2">
                {{ $summaryLabel }}:
                <strong>Rp {{ number_format($summaryValue, 0, ',', '.') }}</strong>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped mb-0">
                <thead>
                    @if($menuKey === 'simpan')
                        <tr>
                            <th>No. Simpan</th>
                            <th>No Rekening</th>
                            <th>Nama Member</th>
                            <th>Jenis Simpanan</th>
                            <th>Jumlah</th>
                            <th>Tanggal Transaksi</th>
                        </tr>
                    @elseif($menuKey === 'pinjam')
                        <tr>
                            <th>No. Pinjam</th>
                            <th>No Rekening</th>
                            <th>Nama Member</th>
                            <th>Pokok</th>
                            <th>Bunga</th>
                            <th>Admin</th>
                            <th>Tenor</th>
                            <th>Tanggal Cair</th>
                        </tr>
                    @elseif($menuKey === 'withdraw')
                        <tr>
                            <th>No. Withdraw</th>
                            <th>No Rekening</th>
                            <th>Nama Member</th>
                            <th>Jumlah</th>
                            <th>Catatan</th>
                            <th>Tanggal Transaksi</th>
                        </tr>
                    @elseif($menuKey === 'angsuran')
                        <tr>
                            <th>No. Angsuran</th>
                            <th>No. Pinjam</th>
                            <th>No Rekening</th>
                            <th>Angsuran Ke</th>
                            <th>Jumlah</th>
                            <th>Tanggal Transaksi</th>
                        </tr>
                    @else
                        <tr>
                            <th>No. Bagi Hasil</th>
                            <th>No. Pinjam</th>
                            <th>No Rekening</th>
                            <th>Nama Member</th>
                            <th>Jumlah Bagi Hasil</th>
                            <th>Tanggal Transaksi</th>
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @if($menuKey === 'simpan')
                            <tr>
                                <td>S-{{ str_pad((string) $row->id, 13, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $row->member_no }}</td>
                                <td>{{ $row->member_name }}</td>
                                <td>{{ ucfirst($row->type) }}</td>
                                <td>Rp {{ number_format((float) $row->amount, 2, ',', '.') }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->transaction_date)->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        @elseif($menuKey === 'pinjam')
                            <tr>
                                <td>{{ $row->loan_no }}</td>
                                <td>{{ $row->member_no }}</td>
                                <td>{{ $row->member_name }}</td>
                                <td>Rp {{ number_format((float) $row->principal_amount, 2, ',', '.') }}</td>
                                <td>{{ number_format((float) $row->interest_percent, 2, ',', '.') }}%</td>
                                <td>Rp {{ number_format((float) $row->admin_fee, 2, ',', '.') }}</td>
                                <td>{{ $row->tenor_months }} bulan</td>
                                <td>{{ \Carbon\Carbon::parse($row->disbursed_at)->format('Y-m-d') }}</td>
                            </tr>
                        @elseif($menuKey === 'withdraw')
                            <tr>
                                <td>W-{{ str_pad((string) $row->id, 13, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $row->member_no }}</td>
                                <td>{{ $row->member_name }}</td>
                                <td>Rp {{ number_format(abs((float) $row->amount), 2, ',', '.') }}</td>
                                <td>{{ $row->note ?: '-' }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->transaction_date)->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        @elseif($menuKey === 'angsuran')
                            <tr>
                                <td>A-{{ str_pad((string) $row->id, 13, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $row->loan_no }}</td>
                                <td>{{ $row->member_no }}</td>
                                <td>{{ $row->installment_no }}</td>
                                <td>Rp {{ number_format((float) $row->amount_total, 2, ',', '.') }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        @else
                            <tr>
                                <td>BH-{{ str_pad((string) $row->id, 13, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $row->loan_no }}</td>
                                <td>{{ $row->member_no }}</td>
                                <td>{{ $row->member_name }}</td>
                                <td>Rp {{ number_format((float) $row->amount_interest, 2, ',', '.') }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ $colspan }}" class="text-center text-muted py-4">
                                Belum ada data transaksi untuk menu {{ strtolower($menuLabel) }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
        <div class="text-muted">
            Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} entries
        </div>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item {{ $rows->onFirstPage() ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $rows->onFirstPage() ? '#' : $rows->previousPageUrl() }}">Previous</a>
            </li>
            <li class="page-item active">
                <span class="page-link">{{ $rows->currentPage() }}</span>
            </li>
            <li class="page-item {{ $rows->hasMorePages() ? '' : 'disabled' }}">
                <a class="page-link" href="{{ $rows->hasMorePages() ? $rows->nextPageUrl() : '#' }}">Next</a>
            </li>
        </ul>
    </div>
</div>

<div class="modal fade" id="modalTambahSimpan" tabindex="-1" role="dialog" aria-labelledby="modalTambahSimpanLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('koperasi.transactions.simpan.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTambahSimpanLabel">Tambah Simpanan</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>No Rekening Member (8 digit)</label>
                        <input type="text"
                               name="member_account_no"
                               class="form-control"
                               list="memberReferenceList"
                               value="{{ old('member_account_no') }}"
                               data-member-account-input
                               data-target-info="#memberInfoSimpan"
                               maxlength="8"
                               required
                               autocomplete="off">
                        <small class="text-muted">Masukkan nomor rekening member yang valid.</small>
                    </div>
                    <div id="memberInfoSimpan" class="border rounded bg-light p-2 small d-none mb-3">
                        <div><strong>Nama:</strong> <span data-field="name"></span></div>
                        <div><strong>NIK:</strong> <span data-field="nik"></span></div>
                        <div><strong>No HP:</strong> <span data-field="phone"></span></div>
                        <div><strong>Status:</strong> <span data-field="status"></span></div>
                    </div>
                    <div class="form-group">
                        <label>Jenis Simpanan</label>
                        <select name="type" class="form-control" required>
                            <option value="pokok" {{ old('type', 'pokok') === 'pokok' ? 'selected' : '' }}>Simpanan Pokok</option>
                            <option value="wajib" {{ old('type') === 'wajib' ? 'selected' : '' }}>Simpanan Wajib</option>
                            <option value="sukarela" {{ old('type') === 'sukarela' ? 'selected' : '' }}>Simpanan Sukarela</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" name="amount" min="1" class="form-control" value="{{ old('amount') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Transaksi</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahPinjam" tabindex="-1" role="dialog" aria-labelledby="modalTambahPinjamLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('koperasi.transactions.pinjam.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTambahPinjamLabel">Tambah Pinjaman</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>No Rekening Member (8 digit)</label>
                        <input type="text"
                               name="member_account_no"
                               class="form-control"
                               list="memberReferenceList"
                               value="{{ old('member_account_no') }}"
                               data-member-account-input
                               data-target-info="#memberInfoPinjam"
                               maxlength="8"
                               required
                               autocomplete="off">
                        <small class="text-muted">Masukkan nomor rekening member yang valid.</small>
                    </div>
                    <div id="memberInfoPinjam" class="border rounded bg-light p-2 small d-none mb-3">
                        <div><strong>Nama:</strong> <span data-field="name"></span></div>
                        <div><strong>NIK:</strong> <span data-field="nik"></span></div>
                        <div><strong>No HP:</strong> <span data-field="phone"></span></div>
                        <div><strong>Status:</strong> <span data-field="status"></span></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>No Pinjaman</label>
                            <input type="text" name="loan_no" class="form-control" value="{{ old('loan_no') }}" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Pokok Pinjaman</label>
                            <input type="number" name="principal_amount" min="1" class="form-control" value="{{ old('principal_amount') }}" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Bunga (%)</label>
                            <input type="number" name="interest_percent" min="0" max="100" step="0.01" class="form-control" value="{{ old('interest_percent', 0) }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Biaya Admin</label>
                            <input type="number" name="admin_fee" min="0" class="form-control" value="{{ old('admin_fee', 0) }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Tenor (bulan)</label>
                            <input type="number" name="tenor_months" min="1" max="240" class="form-control" value="{{ old('tenor_months', 12) }}" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Tanggal Cair</label>
                            <input type="date" name="disbursed_at" class="form-control" value="{{ old('disbursed_at', date('Y-m-d')) }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Jatuh Tempo</label>
                            <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Status</label>
                            <select name="status" class="form-control" required>
                                <option value="berjalan" {{ old('status', 'berjalan') === 'berjalan' ? 'selected' : '' }}>Berjalan</option>
                                <option value="macet" {{ old('status') === 'macet' ? 'selected' : '' }}>Macet</option>
                                <option value="lunas" {{ old('status') === 'lunas' ? 'selected' : '' }}>Lunas</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahWithdraw" tabindex="-1" role="dialog" aria-labelledby="modalTambahWithdrawLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('koperasi.transactions.withdraw.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTambahWithdrawLabel">Tambah Withdraw</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>No Rekening Member (8 digit)</label>
                        <input type="text"
                               name="member_account_no"
                               class="form-control"
                               list="memberReferenceList"
                               value="{{ old('member_account_no') }}"
                               data-member-account-input
                               data-target-info="#memberInfoWithdraw"
                               maxlength="8"
                               required
                               autocomplete="off">
                        <small class="text-muted">Masukkan nomor rekening member yang valid.</small>
                    </div>
                    <div id="memberInfoWithdraw" class="border rounded bg-light p-2 small d-none mb-3">
                        <div><strong>Nama:</strong> <span data-field="name"></span></div>
                        <div><strong>NIK:</strong> <span data-field="nik"></span></div>
                        <div><strong>No HP:</strong> <span data-field="phone"></span></div>
                        <div><strong>Status:</strong> <span data-field="status"></span></div>
                    </div>
                    <div class="form-group">
                        <label>Nominal Withdraw</label>
                        <input type="number" name="amount" min="1" class="form-control" value="{{ old('amount') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Transaksi</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-danger">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahAngsuran" tabindex="-1" role="dialog" aria-labelledby="modalTambahAngsuranLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('koperasi.transactions.angsuran.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTambahAngsuranLabel">Tambah Angsuran</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>No Rekening Member (8 digit)</label>
                        <input type="text"
                               name="member_account_no"
                               class="form-control"
                               list="memberReferenceList"
                               value="{{ old('member_account_no') }}"
                               data-member-account-input
                               data-target-info="#memberInfoAngsuran"
                               maxlength="8"
                               required
                               autocomplete="off">
                        <small class="text-muted">Setelah nomor rekening valid, data member akan tampil otomatis.</small>
                    </div>
                    <div id="memberInfoAngsuran" class="border rounded bg-light p-2 small d-none mb-3">
                        <div><strong>Nama:</strong> <span data-field="name"></span></div>
                        <div><strong>NIK:</strong> <span data-field="nik"></span></div>
                        <div><strong>No HP:</strong> <span data-field="phone"></span></div>
                        <div><strong>Status:</strong> <span data-field="status"></span></div>
                    </div>
                    <div class="form-group">
                        <label>No Pinjaman (opsional jika hanya ada 1 pinjaman aktif)</label>
                        <input type="text" name="loan_no" class="form-control" list="loanReferenceList" value="{{ old('loan_no') }}" autocomplete="off">
                        <small class="text-muted">Isi bila member memiliki lebih dari satu pinjaman aktif.</small>
                    </div>
                    <div class="form-group">
                        <label>Angsuran Ke (opsional)</label>
                        <input type="number" name="installment_no" min="1" class="form-control" value="{{ old('installment_no') }}">
                    </div>
                    <div class="form-group">
                        <label>Nominal Angsuran</label>
                        <input type="number" name="amount_total" min="0.01" step="0.01" class="form-control" value="{{ old('amount_total') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Denda</label>
                        <input type="number" name="amount_penalty" min="0" step="0.01" class="form-control" value="{{ old('amount_penalty', 0) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Bayar</label>
                        <input type="date" name="paid_at" class="form-control" value="{{ old('paid_at', date('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const memberMap = @json($memberReferenceMap);

        const normalizeAccountNo = function (value) {
            return String(value || '').replace(/\D/g, '').slice(0, 8);
        };

        const renderMemberInfo = function (selector, member) {
            const box = document.querySelector(selector);
            if (!box) {
                return;
            }

            if (!member) {
                box.classList.remove('d-none');
                box.classList.add('border-danger');
                box.classList.remove('bg-light');
                box.innerHTML = '<span class="text-danger">Nomor rekening tidak valid atau tidak ditemukan.</span>';
                return;
            }

            box.classList.remove('d-none', 'border-danger');
            box.classList.add('bg-light');
            box.innerHTML = ''
                + '<div><strong>No Rekening:</strong> ' + member.account_no + '</div>'
                + '<div><strong>Nama:</strong> ' + member.name + '</div>'
                + '<div><strong>NIK:</strong> ' + (member.nik || '-') + '</div>'
                + '<div><strong>No HP:</strong> ' + (member.phone || '-') + '</div>'
                + '<div><strong>Status:</strong> ' + (member.status || '-') + '</div>';
        };

        document.querySelectorAll('[data-member-account-input]').forEach(function (input) {
            const targetInfo = input.getAttribute('data-target-info');
            const sync = function () {
                const normalized = normalizeAccountNo(input.value);
                if (input.value !== normalized) {
                    input.value = normalized;
                }

                if (!targetInfo) {
                    return;
                }

                if (normalized.length !== 8) {
                    const infoBox = document.querySelector(targetInfo);
                    if (infoBox) {
                        infoBox.classList.add('d-none');
                    }
                    return;
                }

                renderMemberInfo(targetInfo, memberMap[normalized] || null);
            };

            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            sync();
        });

        document.querySelectorAll('[data-bs-toggle="modal"], [data-toggle="modal"]').forEach(function (button) {
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
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
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
