@extends('layouts.app')

@section('title','Tambah Budget')

@section('content')
<div class="card">
    <div class="card-body">

        <form method="POST" action="{{ route('budgets.store') }}">
            @csrf

            {{-- KATEGORI PENGELUARAN --}}
            <div class="form-group">
                <label>Kategori Pengeluaran</label>
                <select name="category_id"
                        class="form-control @error('category_id') is-invalid @enderror"
                        required>
                    <option value="">-- Pilih Kategori --</option>

                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}"
                            {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>

                @error('category_id')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            {{-- LIMIT --}}
            <div class="form-group">
                <label>Limit (Rp)</label>
                <input type="number"
                       name="limit"
                       value="{{ old('limit') }}"
                       class="form-control @error('limit') is-invalid @enderror"
                       required>

                @error('limit')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            {{-- BULAN --}}
            <div class="form-group">
                <label>Bulan</label>
                <select name="month"
                        class="form-control @error('month') is-invalid @enderror">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}"
                            {{ old('month', date('n')) == $m ? 'selected' : '' }}>
                            {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                        </option>
                    @endfor
                </select>

                @error('month')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            {{-- TAHUN --}}
            <div class="form-group">
                <label>Tahun</label>
                <input type="number"
                       name="year"
                       value="{{ old('year', date('Y')) }}"
                       class="form-control @error('year') is-invalid @enderror">

                @error('year')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <button class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>

            <a href="{{ route('budgets.index') }}"
               class="btn btn-secondary">
                Kembali
            </a>

        </form>

    </div>
</div>
@endsection
