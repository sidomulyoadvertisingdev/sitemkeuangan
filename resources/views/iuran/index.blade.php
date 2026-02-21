@extends('layouts.app')

@section('title', 'Iuran Anggota')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex flex-wrap align-items-center">
            <a href="{{ route('iuran.create') }}" class="btn btn-primary btn-sm mr-2 mb-2">
                <i class="fas fa-plus"></i> Tambah Anggota
            </a>

            @if(auth()->user()->hasPermission('iuran.import'))
                <a href="{{ route('iuran.import.template') }}" class="btn btn-outline-secondary btn-sm mr-2 mb-2">
                    <i class="fas fa-file-csv"></i> Template Import Anggota
                </a>

                <a href="{{ route('iuran.export.pdf') }}" class="btn btn-danger btn-sm mr-2 mb-2">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>

                <form action="{{ route('iuran.import') }}" method="POST" enctype="multipart/form-data" class="form-inline mb-2">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" class="form-control form-control-sm mr-2" required>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-upload"></i> Import Anggota
                    </button>
                </form>

                <a href="{{ route('iuran.installments.import.template') }}" class="btn btn-outline-info btn-sm mr-2 mb-2">
                    <i class="fas fa-file-csv"></i> Template Import Cicilan
                </a>

                <form action="{{ route('iuran.installments.import') }}" method="POST" enctype="multipart/form-data" class="form-inline mb-2">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" class="form-control form-control-sm mr-2" required>
                    <button type="submit" class="btn btn-info btn-sm">
                        <i class="fas fa-upload"></i> Import Cicilan Massal
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('iuran.index') }}" class="mb-3">
            <div class="input-group input-group-sm" style="max-width: 420px;">
                <input type="text"
                       name="q"
                       class="form-control"
                       placeholder="Cari nama anggota..."
                       value="{{ $q ?? '' }}">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="{{ route('iuran.index') }}" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('import_warnings'))
            <div class="alert alert-warning mb-3">
                <strong>Import anggota, beberapa baris dilewati:</strong>
                @foreach(session('import_warnings') as $warning)
                    <div>{{ $warning }}</div>
                @endforeach
            </div>
        @endif

        @if(session('import_installment_warnings'))
            <div class="alert alert-warning mb-3">
                <strong>Import cicilan, beberapa baris dilewati:</strong>
                @foreach(session('import_installment_warnings') as $warning)
                    <div>{{ $warning }}</div>
                @endforeach
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger mb-3">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Nama Anggota</th>
                    <th>Target</th>
                    <th>Periode Target</th>
                    <th>Terbayar</th>
                    <th>Sisa</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th width="180">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>Rp {{ number_format($item->target_amount,0,',','.') }}</td>
                        <td>{{ $item->target_period }}</td>
                        <td>Rp {{ number_format($item->paid_amount ?? 0,0,',','.') }}</td>
                        <td>Rp {{ number_format($item->remaining_amount,0,',','.') }}</td>
                        <td>
                            <div class="progress progress-sm mb-1">
                                <div class="progress-bar {{ $item->progress >= 100 ? 'bg-success' : 'bg-info' }}" style="width: {{ $item->progress }}%"></div>
                            </div>
                            <small>{{ $item->progress }}%</small>
                        </td>
                        <td>
                            <span class="badge {{ $item->status === 'lunas' ? 'badge-success' : 'badge-warning' }}">
                                {{ $item->status === 'lunas' ? 'Lunas' : 'Aktif' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('iuran.show', $item) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('iuran.edit', $item) }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('iuran.destroy', $item) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Hapus data anggota ini?')">
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
                        <td colspan="8" class="text-center">Belum ada data iuran anggota</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
