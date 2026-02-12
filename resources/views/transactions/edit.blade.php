@extends('layouts.app')

@section('title', 'Edit Transaksi')

@section('content')
<div class="card">
    <div class="card-body">

        <form method="POST" action="{{ route('transactions.update', $transaction) }}">
            @csrf
            @method('PUT')

            {{-- JENIS --}}
            <div class="form-group">
                <label>Jenis</label>
                <select name="type" class="form-control" disabled>
                    <option value="income" {{ $transaction->type == 'income' ? 'selected' : '' }}>
                        Pemasukan
                    </option>
                    <option value="expense" {{ $transaction->type == 'expense' ? 'selected' : '' }}>
                        Pengeluaran
                    </option>
                </select>
                {{-- kirim value walau disabled --}}
                <input type="hidden" name="type" value="{{ $transaction->type }}">
            </div>

            {{-- REKENING --}}
            <div class="form-group">
                <label>Rekening</label>
                <select name="bank_account_id" class="form-control" required>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ $transaction->bank_account_id == $acc->id ? 'selected' : '' }}>
                            {{ $acc->name }} {{ $acc->account_number ? '('.$acc->account_number.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- PROYEK (OPSIONAL) --}}
            <div class="form-group">
                <label>Proyek (opsional)</label>
                <select name="project_id" class="form-control">
                    <option value="">-- Tanpa Proyek --</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ $transaction->project_id == $project->id ? 'selected' : '' }}>
                            {{ $project->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- KATEGORI --}}
            <div class="form-group">
                <label>Kategori</label>
                <div class="input-group">
                    <select name="category_id" id="categorySelect"
                            class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                                {{ $transaction->category_id == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="input-group-append">
                        <button type="button"
                                class="btn btn-outline-primary btn-add-category"
                                data-toggle="modal"
                                data-target="#addCategoryModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted">
                    Tambah kategori baru tanpa meninggalkan halaman ini.
                </small>
            </div>

            {{-- NOMINAL --}}
            <div class="form-group">
                <label>Nominal</label>
                <input type="number"
                       name="amount"
                       value="{{ $transaction->amount }}"
                       class="form-control"
                       required>
            </div>

            {{-- TANGGAL --}}
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date"
                       name="date"
                       value="{{ $transaction->date }}"
                       class="form-control"
                       required>
            </div>

            {{-- CATATAN --}}
            <div class="form-group">
                <label>Catatan</label>
                <textarea name="note" class="form-control">{{ $transaction->note }}</textarea>
            </div>

            <button class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>

            <a href="{{ route('transactions.index') }}" class="btn btn-secondary">
                Kembali
            </a>
        </form>

</div>
</div>

{{-- MODAL TAMBAH KATEGORI --}}
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Kategori</h5>
                <button class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nama Kategori</label>
                    <input type="text" id="newCategoryName" class="form-control">
                </div>
                <p class="text-muted mb-0">Jenis otomatis: {{ $transaction->type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="saveCategoryBtn">Simpan</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const categorySelect = document.getElementById('categorySelect');
    const typeValue = "{{ $transaction->type }}";

    // Fallback buka modal manual jika data-* tidak memicu
    document.querySelectorAll('.btn-add-category').forEach(btn => {
        btn.addEventListener('click', () => $('#addCategoryModal').modal('show'));
    });

    document.getElementById('saveCategoryBtn').addEventListener('click', async () => {
        const name = document.getElementById('newCategoryName').value.trim();
        if (!name) {
            alert('Isi nama kategori');
            return;
        }
        try {
            const res = await fetch("{{ route('categories.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ name, type: typeValue })
            });
            if (!res.ok) throw new Error('Gagal menambah kategori');
            const cat = await res.json();

            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.text  = cat.name;
            opt.selected = true;
            categorySelect.appendChild(opt);

            $('#addCategoryModal').modal('hide');
            document.getElementById('newCategoryName').value = '';
        } catch (err) {
            alert(err.message);
        }
    });
</script>
@endpush
