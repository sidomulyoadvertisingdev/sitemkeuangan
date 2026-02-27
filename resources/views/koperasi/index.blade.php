@extends('layouts.app')

@section('title', 'Koperasi Simpan Pinjam')

@section('content')
<div class="row mb-3">
    <div class="col-md-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#22c55e33,#22c55e55);">
            <div class="inner">
                <h3>{{ $summary['total_members'] }}</h3>
                <p>Total Member</p>
            </div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#06b6d433,#06b6d455);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['total_savings'],0,',','.') }}</h3>
                <p>Total Simpanan</p>
            </div>
            <div class="icon"><i class="fas fa-piggy-bank"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#2563eb33,#2563eb55);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['total_loan_disbursed'],0,',','.') }}</h3>
                <p>Total Pinjaman Cair</p>
            </div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#f59e0b33,#f59e0b55);">
            <div class="inner">
                <h3>Rp {{ number_format($summary['total_loan_outstanding'],0,',','.') }}</h3>
                <p>Sisa Pinjaman</p>
            </div>
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center">
        <a href="{{ route('koperasi.dashboard') }}" class="btn btn-info btn-sm mr-2 mb-2">
            <i class="fas fa-chart-pie"></i> Dashboard Koperasi
        </a>
        <a href="{{ route('koperasi.transactions', ['menu' => 'angsuran']) }}" class="btn btn-secondary btn-sm mr-2 mb-2">
            <i class="fas fa-exchange-alt"></i> Menu Transaction
        </a>
        <a href="{{ route('koperasi.create') }}" class="btn btn-primary btn-sm mr-2 mb-2">
            <i class="fas fa-plus"></i> Tambah Member
        </a>
        <a href="{{ route('koperasi.export.pdf', ['q' => $q]) }}" class="btn btn-danger btn-sm mr-2 mb-2">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
        <a href="{{ route('koperasi.export.excel', ['q' => $q]) }}" class="btn btn-success btn-sm mr-2 mb-2">
            <i class="fas fa-file-excel"></i> Export Excel
        </a>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('koperasi.index') }}" class="mb-3">
            <div class="input-group input-group-sm" style="max-width: 420px;">
                <input type="text"
                       name="q"
                       class="form-control"
                       placeholder="Cari nomor atau nama member..."
                       value="{{ $q ?? '' }}">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="{{ route('koperasi.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

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

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No Rekening</th>
                    <th>Nama</th>
                    <th>Gabung</th>
                    <th>Total Simpanan</th>
                    <th>Pinjaman Cair</th>
                    <th>Sisa Pinjaman</th>
                    <th>Status</th>
                    <th width="180">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $member)
                    <tr>
                        <td>{{ $member->member_no }}</td>
                        <td>{{ $member->name }}</td>
                        <td>{{ \Carbon\Carbon::parse($member->join_date)->format('d-m-Y') }}</td>
                        <td>Rp {{ number_format($member->savings_total ?? 0,0,',','.') }}</td>
                        <td>Rp {{ number_format($member->loan_disbursed,0,',','.') }}</td>
                        <td>Rp {{ number_format($member->loan_outstanding,0,',','.') }}</td>
                        <td>
                            <span class="badge {{ $member->status === 'aktif' ? 'badge-success' : 'badge-secondary' }}">
                                {{ ucfirst($member->status) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('koperasi.show', $member) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('koperasi.edit', $member) }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('koperasi.destroy', $member) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Hapus member ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">Belum ada data member koperasi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
