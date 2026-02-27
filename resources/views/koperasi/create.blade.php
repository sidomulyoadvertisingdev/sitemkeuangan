@extends('layouts.app')

@section('title', 'Tambah Member Koperasi')

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

        <form method="POST" action="{{ route('koperasi.store') }}">
            @csrf

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>No Rekening Member</label>
                    <input type="text" class="form-control" value="Otomatis dibuat sistem (8 digit unik)" readonly>
                    <small class="text-muted">Nomor rekening akan dibuat otomatis saat data disimpan.</small>
                </div>
                <div class="form-group col-md-8">
                    <label>Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>NIK</label>
                    <input type="text" name="nik" class="form-control" value="{{ old('nik') }}">
                </div>
                <div class="form-group col-md-4">
                    <label>Jenis Kelamin</label>
                    <select name="gender" class="form-control">
                        <option value="">-- Pilih --</option>
                        <option value="L" {{ old('gender') === 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('gender') === 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>No HP</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Tanggal Gabung</label>
                    <input type="date" name="join_date" class="form-control" value="{{ old('join_date', date('Y-m-d')) }}" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="aktif" {{ old('status', 'aktif') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="nonaktif" {{ old('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
            </div>

            <div class="form-group">
                <label>Catatan</label>
                <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
            </div>

            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('koperasi.index') }}" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</div>
@endsection
