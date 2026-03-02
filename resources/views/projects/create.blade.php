@extends('layouts.app')

@section('title', 'Proyek Baru')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('projects.store') }}">
            @csrf

            <div class="form-group">
                <label>Nama Proyek</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>

            <div class="form-group">
                <label>Rekening Penyimpanan</label>
                <select name="bank_account_id" class="form-control" required>
                    <option value="">-- Pilih Rekening --</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ (string) old('bank_account_id') === (string) $acc->id ? 'selected' : '' }}>{{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Target Dana</label>
                <input type="number" name="target_amount" class="form-control" value="{{ old('target_amount') }}" min="1" required>
                <small class="text-muted">Total kebutuhan dana proyek.</small>
            </div>

            <div class="form-group">
                <label>Mode Pembagian Iuran</label>
                <select name="iuran_allocation_mode" class="form-control">
                    <option value="default" {{ old('iuran_allocation_mode', 'default') === 'default' ? 'selected' : '' }}>Default (rata)</option>
                    <option value="kelas" {{ old('iuran_allocation_mode') === 'kelas' ? 'selected' : '' }}>Kelas (berdasarkan persentase kelas)</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Kelas A (%)</label>
                    <input type="number" name="iuran_class_a_percent" class="form-control" value="{{ old('iuran_class_a_percent', 130) }}" min="1" step="0.01">
                </div>
                <div class="form-group col-md-4">
                    <label>Kelas B (%)</label>
                    <input type="number" name="iuran_class_b_percent" class="form-control" value="{{ old('iuran_class_b_percent', 110) }}" min="1" step="0.01">
                </div>
                <div class="form-group col-md-4">
                    <label>Kelas C (%)</label>
                    <input type="number" name="iuran_class_c_percent" class="form-control" value="{{ old('iuran_class_c_percent', 100) }}" min="1" step="0.01">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Mulai</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}">
                </div>
                <div class="form-group col-md-6">
                    <label>Selesai</label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}">
                </div>
            </div>

            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>

            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('projects.index') }}" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
@endsection
