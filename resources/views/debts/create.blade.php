@extends('layouts.app')

@section('title','Tambah Hutang / Piutang')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('debts.store') }}">
            @csrf

            <div class="form-group">
                <label>Jenis</label>
                <select name="type" class="form-control" required>
                    <option value="">-- Pilih --</option>
                    <option value="hutang">Hutang</option>
                    <option value="piutang">Piutang</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nama</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Nominal</label>
                <input type="number" name="amount" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Jatuh Tempo</label>
                <input type="date" name="due_date" class="form-control">
            </div>

            <div class="form-group">
                <label>Catatan</label>
                <textarea name="note" class="form-control"></textarea>
            </div>

            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('debts.index') }}" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</div>
@endsection
