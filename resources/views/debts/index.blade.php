@extends('layouts.app')

@section('title','Hutang & Piutang')

@section('content')
<div class="card">
    <div class="card-header">
        <a href="{{ route('debts.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Data
        </a>
    </div>

    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>Nama</th>
                    <th>Nominal</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th width="150">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($debts as $item)
                <tr>
                    <td>
                        <span class="badge {{ $item->type == 'hutang' ? 'badge-danger' : 'badge-success' }}">
                            {{ ucfirst($item->type) }}
                        </span>
                    </td>
                    <td>{{ $item->name }}</td>
                    <td>Rp {{ number_format($item->amount,0,',','.') }}</td>
                    <td>{{ $item->due_date ?? '-' }}</td>
                    <td>
                        <span class="badge {{ $item->status == 'lunas' ? 'badge-success' : 'badge-warning' }}">
                            {{ $item->status == 'lunas' ? 'Lunas' : 'Belum Lunas' }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('debts.show', $item) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>

                        <form action="{{ route('debts.destroy', $item) }}"
                              method="POST"
                              class="d-inline"
                              onsubmit="return confirm('Hapus data ini?')">
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
                    <td colspan="6" class="text-center">Belum ada data</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
