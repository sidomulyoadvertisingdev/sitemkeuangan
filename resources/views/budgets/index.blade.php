@extends('layouts.app')

@section('title','Budget Bulanan')

@section('content')

{{-- FILTER BULAN & TAHUN --}}
<div class="card mb-3">
    <div class="card-body">
        <form class="form-inline" method="GET">
            <select name="month" class="form-control mr-2">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>
                        {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                    </option>
                @endfor
            </select>

            <input type="number"
                   name="year"
                   value="{{ $year }}"
                   class="form-control mr-2"
                   style="width:120px">

            <button class="btn btn-primary btn-sm">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>
</div>

{{-- LIST BUDGET --}}
<div class="card">
    <div class="card-header">
        <a href="{{ route('budgets.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Budget
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
                    <th>Kategori</th>
                    <th>Limit</th>
                    <th>Terpakai</th>
                    <th>Sisa</th>
                    <th>Progress</th>
                    <th width="80">Aksi</th>
                </tr>
            </thead>

            <tbody>
            @forelse($budgets as $b)

                @php
                    $percent = $b->limit > 0 ? ($b->used / $b->limit) * 100 : 0;

                    if ($percent >= 100) {
                        $bar = 'bg-danger';
                    } elseif ($percent >= 80) {
                        $bar = 'bg-warning';
                    } else {
                        $bar = 'bg-success';
                    }
                @endphp

                <tr>
                    <td>
                        {{ $b->category->name ?? '-' }}
                    </td>

                    <td>
                        Rp {{ number_format($b->limit, 0, ',', '.') }}
                    </td>

                    <td>
                        Rp {{ number_format($b->used, 0, ',', '.') }}
                    </td>

                    <td class="{{ $b->remaining <= 0 ? 'text-danger font-weight-bold' : '' }}">
                        Rp {{ number_format($b->remaining, 0, ',', '.') }}
                    </td>

                    <td>
                        <div class="progress">
                            <div class="progress-bar {{ $bar }}"
                                 style="width: {{ min($percent, 100) }}%">
                                {{ number_format($percent, 1) }}%
                            </div>
                        </div>
                    </td>

                    <td>
                        <form action="{{ route('budgets.destroy', $b) }}"
                              method="POST"
                              onsubmit="return confirm('Hapus budget ini?')">
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
                    <td colspan="6" class="text-center text-muted">
                        Belum ada budget untuk periode ini
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>

    </div>
</div>

@endsection
