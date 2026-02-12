@extends('layouts.app')

@section('title','Investasi')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Portofolio Investasi</h5>
        <a href="{{ route('investments.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Alokasi Dana
        </a>
    </div>
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">{{ session('success') }}</div>
        @endif
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Aset</th>
                    <th>Kategori</th>
                    <th>Rekening</th>
                    <th>Nominal (IDR)</th>
                    <th>Qty</th>
                    <th>Harga Saat Ini</th>
                    <th>Nilai Saat Ini</th>
                    <th>P/L</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allocations as $item)
                    <tr>
                        <td>{{ $item->asset->name }}</td>
                        <td><span class="badge badge-info text-uppercase">{{ $item->asset->category }}</span></td>
                        <td>{{ $item->bankAccount?->name }}</td>
                        <td>Rp {{ number_format($item->amount_fiat,0,',','.') }}</td>
                        <td>{{ $item->quantity ? number_format($item->quantity,8) : '-' }}</td>
                        <td>
                            @if($item->current_price)
                                Rp {{ number_format($item->current_price,0,',','.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($item->current_value)
                                Rp {{ number_format($item->current_value,0,',','.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if(!is_null($item->pnl))
                                <span class="{{ $item->pnl >=0 ? 'text-success' : 'text-danger' }}">
                                    {{ $item->pnl >=0 ? '+' : '' }}Rp {{ number_format($item->pnl,0,',','.') }}
                                </span>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $item->executed_at->format('d-m-Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">Belum ada alokasi investasi</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
