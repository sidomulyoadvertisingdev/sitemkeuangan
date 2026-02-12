@extends('layouts.app')

@section('title', 'Tambah Transaksi')

@section('content')
<div class="card">
    <div class="card-body">

        <form method="POST" action="{{ route('transactions.store') }}">
            @csrf

            {{-- JENIS --}}
            <div class="form-group">
                <label>Jenis</label>
                <select name="type" id="typeSelect" class="form-control" required>
                    <option value="">-- Pilih --</option>
                    <option value="income">Pemasukan</option>
                    <option value="expense">Pengeluaran</option>
                </select>
            </div>

            {{-- KATEGORI --}}
            <div class="form-group">
                <label>Kategori</label>
                <div class="input-group">
                    <select name="category_id" id="categorySelect"
                            class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        {{-- Akan diisi via JS --}}
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
                    Jika kategori belum ada, klik tombol + untuk menambah tanpa keluar halaman.
                </small>
            </div>

            {{-- REKENING --}}
            <div class="form-group">
                <label>Rekening</label>
                <select name="bank_account_id" class="form-control" required>
                    <option value="">-- Pilih Rekening --</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }} {{ $acc->account_number ? '('.$acc->account_number.')' : '' }}</option>
                    @endforeach
                </select>
            </div>

            {{-- PROYEK (OPSIONAL) --}}
            <div class="form-group">
                <label>Proyek (opsional)</label>
                <select name="project_id" class="form-control">
                    <option value="">-- Tanpa Proyek --</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- NOMINAL --}}
            <div class="form-group">
                <label>Nominal</label>
                <input type="number" name="amount" class="form-control" required value="{{ old('amount') }}">
            </div>

            {{-- TANGGAL --}}
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="date" class="form-control"
                       value="{{ date('Y-m-d') }}" required>
            </div>

            {{-- CATATAN --}}
            <div class="form-group">
                <label>Catatan</label>
                <textarea name="note" class="form-control"></textarea>
            </div>

            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary">
                Kembali
            </a>

        </form>
    </div>
</div>

{{-- ================= MODAL TAMBAH KATEGORI ================= --}}
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
                    <input type="text" id="newCategoryName"
                           class="form-control">
                </div>
                <p class="text-muted mb-0">Jenis mengikuti pilihan di form transaksi.</p>
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-primary"
                        id="saveCategoryBtn">
                    Simpan
                </button>
            </div>

        </div>
    </div>
</div>
@endsection


@push('scripts')
<script>
    const typeSelect     = document.getElementById('typeSelect');
    const categorySelect = document.getElementById('categorySelect');

    function renderCategories(list, selectedId = null) {
        categorySelect.innerHTML = '<option value="">-- Pilih Kategori --</option>';
        list.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.text  = cat.name;
            if (Number(selectedId) === Number(cat.id)) opt.selected = true;
            categorySelect.appendChild(opt);
        });
    }

    async function loadCategories(type, selectedId = null) {
        if (!type) {
            renderCategories([]);
            return;
        }
        try {
            const res = await fetch(`/categories/by-type/${type}`);
            if (!res.ok) throw new Error('Gagal memuat kategori');
            const data = await res.json();
            renderCategories(data, selectedId);
        } catch (err) {
            alert(err.message);
        }
    }

    typeSelect.addEventListener('change', function () {
        loadCategories(this.value);
    });

    // Fallback jika data-* tidak jalan, buka modal via JS
    document.querySelectorAll('.btn-add-category').forEach(btn => {
        btn.addEventListener('click', () => {
            $('#addCategoryModal').modal('show');
        });
    });

    // Prefill jika kembali dari validasi atau default
    document.addEventListener('DOMContentLoaded', () => {
        const initialType = "{{ old('type') }}";
        const initialCat  = "{{ old('category_id') }}";
        if (initialType) {
            typeSelect.value = initialType;
            loadCategories(initialType, initialCat);
        }
    });

    // ================= SIMPAN KATEGORI BARU =================
    document.getElementById('saveCategoryBtn')
        .addEventListener('click', async function () {

            const name = document.getElementById('newCategoryName').value.trim();
            const type = typeSelect.value;

            if (!name || !type) {
                alert('Pilih jenis & isi nama kategori');
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
                    body: JSON.stringify({ name, type })
                });

                if (!res.ok) {
                    const msg = await res.text();
                    throw new Error('Gagal menambah kategori: ' + msg);
                }

                const cat = await res.json();
                // reload supaya urutan alfabet terjaga
                await loadCategories(type, cat.id);

                $('#addCategoryModal').modal('hide');
                document.getElementById('newCategoryName').value = '';
            } catch (err) {
                alert(err.message);
            }
        });
</script>
@endpush
