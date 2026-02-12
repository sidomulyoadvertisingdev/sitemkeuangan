@extends('layouts.app')

@section('title', 'Transaksi')

@section('content')
<div class="card">

    <div class="card-header">
        <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Transaksi
        </a>
    </div>

    <div class="card-body">

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th width="120">Tanggal</th>
                    <th width="120">Jenis</th>
                    <th>Kategori</th>
                    <th>Rekening</th>
                    <th>Proyek</th>
                    <th width="150">Nominal</th>
                    <th>Catatan</th>
                    <th width="120">Aksi</th>
                </tr>
            </thead>
            <tbody>

                @forelse($transactions as $item)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($item->date)->format('d-m-Y') }}</td>

                    <td>
                        @if($item->type === 'income')
                            <span class="badge badge-success">Pemasukan</span>
                        @else
                            <span class="badge badge-danger">Pengeluaran</span>
                        @endif
                    </td>

                    <td>
                        {{ $item->category?->name ?? '-' }}
                    </td>

                    <td>{{ $item->bankAccount?->name ?? '-' }}</td>
                    <td>{{ $item->project?->name ?? '-' }}</td>

                    <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>

                    <td>
                        {{ $item->note ?? '-' }}
                    </td>

                    <td class="text-center">

                        <a href="{{ route('transactions.edit', $item) }}"
                           class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i>
                        </a>

                        <form action="{{ route('transactions.destroy', $item) }}"
                              method="POST"
                              class="d-inline"
                              onsubmit="return confirm('Hapus transaksi ini?')">
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
                    <td colspan="6" class="text-center">
                        Belum ada transaksi
                    </td>
                </tr>
                @endforelse

            </tbody>
        </table>

    </div>
</div>
@endsection
