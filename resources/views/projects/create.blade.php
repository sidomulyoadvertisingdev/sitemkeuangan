@extends('layouts.app')

@section('title', 'Proyek Baru')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('projects.store') }}">
            @csrf

            <div class="form-group">
                <label>Nama Proyek</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Rekening Penyimpanan</label>
                <select name="bank_account_id" class="form-control" required>
                    <option value="">-- Pilih Rekening --</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Target Dana</label>
                <input type="number" name="target_amount" class="form-control" value="0">
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Mulai</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
                <div class="form-group col-md-6">
                    <label>Selesai</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('projects.index') }}" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
@endsection
