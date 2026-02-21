@extends('layouts.app')

@section('title', 'Edit Target Iuran')

@section('content')
<div class="card">
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('iuran.update', $iuran) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Nama Anggota</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $iuran->name) }}" required>
            </div>

            <div class="form-group">
                <label>Target Iuran</label>
                <input type="number" name="target_amount" class="form-control" value="{{ old('target_amount', $iuran->target_amount) }}" min="1" required>
            </div>

            <div class="form-group">
                <label>Periode Mulai (Tahun)</label>
                <input type="number" name="target_start_year" class="form-control" value="{{ old('target_start_year', $iuran->target_start_year) }}" min="2000" required>
            </div>

            <div class="form-group">
                <label>Periode Sampai Tahun</label>
                <input type="number" name="target_end_year" class="form-control" value="{{ old('target_end_year', $iuran->target_end_year) }}" min="2000" required>
            </div>

            <div class="form-group">
                <label>Catatan</label>
                <textarea name="note" class="form-control">{{ old('note', $iuran->note) }}</textarea>
            </div>

            <button class="btn btn-primary">Update</button>
            <a href="{{ route('iuran.index') }}" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</div>
@endsection
