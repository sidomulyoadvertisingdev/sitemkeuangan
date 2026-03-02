@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-1">{{ $project->name }}</h5>
                <p class="text-muted mb-2">{{ $project->description ?? 'Tidak ada deskripsi' }}</p>
                <p class="mb-1"><strong>Rekening:</strong> {{ $project->bankAccount?->name ?? '-' }}</p>
                <p class="mb-1"><strong>Target:</strong> Rp {{ number_format($project->target_amount,0,',','.') }}</p>
                <p class="mb-1"><strong>Mode Iuran:</strong> {{ $project->iuran_allocation_mode === 'kelas' ? 'Kelas' : 'Default (Rata)' }}</p>
                <p class="mb-1"><strong>Persen Kelas:</strong> A {{ number_format((float) $project->iuran_class_a_percent, 0, ',', '.') }}% |
                    B {{ number_format((float) $project->iuran_class_b_percent, 0, ',', '.') }}% |
                    C {{ number_format((float) $project->iuran_class_c_percent, 0, ',', '.') }}%</p>
                <hr class="my-2">
                <p class="mb-1"><strong>Target Dana Masuk (Iuran):</strong> Rp {{ number_format($incomingTarget,0,',','.') }}</p>
                <p class="mb-1"><strong>Dana Masuk Tercapai:</strong> Rp {{ number_format($iuranCollected,0,',','.') }}</p>
                <p class="mb-1"><strong>Dana Keluar Proyek:</strong> Rp {{ number_format($netSpent,0,',','.') }}</p>
                <p class="mb-1"><strong>Jumlah Setoran Iuran:</strong> {{ number_format($iuranInstallmentCount,0,',','.') }} cicilan ({{ number_format($iuranMemberCount,0,',','.') }} anggota)</p>
                <p class="mb-1"><strong>Selisih Target Masuk:</strong>
                    @if($incomingGap > 0)
                        <span class="text-warning">Kurang Rp {{ number_format($incomingGap,0,',','.') }}</span>
                    @elseif($incomingGap < 0)
                        <span class="text-success">Lebih Rp {{ number_format(abs($incomingGap),0,',','.') }}</span>
                    @else
                        <span class="text-success">Target masuk tercapai</span>
                    @endif
                </p>
                <p class="mb-1"><strong>Selisih Masuk vs Keluar:</strong>
                    @if($cashflowGap >= 0)
                        <span class="text-success">Surplus Rp {{ number_format($cashflowGap,0,',','.') }}</span>
                    @else
                        <span class="text-danger">Defisit Rp {{ number_format(abs($cashflowGap),0,',','.') }}</span>
                    @endif
                </p>
                <p class="mb-1"><strong>Dana Dialokasikan:</strong> Rp {{ number_format($allocated,0,',','.') }}</p>
                <p class="mb-1"><strong>Terpakai (net):</strong> Rp {{ number_format($netSpent,0,',','.') }}</p>
                <p class="mb-1"><strong>Sisa:</strong> Rp {{ number_format($balance,0,',','.') }}</p>
                <p class="mb-1"><strong>Total Jatah Iuran:</strong> Rp {{ number_format($plannedTotal,0,',','.') }}</p>
                <p class="mb-1"><strong>Selisih vs Target:</strong>
                    @if($plannedGap > 0)
                        <span class="text-warning">Kurang Rp {{ number_format($plannedGap,0,',','.') }}</span>
                    @elseif($plannedGap < 0)
                        <span class="text-danger">Lebih Rp {{ number_format(abs($plannedGap),0,',','.') }}</span>
                    @else
                        <span class="text-success">Pas sesuai target</span>
                    @endif
                </p>
                <div class="progress mt-2" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: {{ $incomingProgress }}%"></div>
                </div>
                <small class="text-muted">Progress target dana masuk iuran: {{ $incomingProgress }}%</small>
                <div class="progress mt-2" style="height: 10px;">
                    <div class="progress-bar" style="width: {{ $progress }}%"></div>
                </div>
                <small class="text-muted">Progress penggunaan dana terhadap target: {{ $progress }}%</small>
                <form action="{{ route('projects.destroy', $project) }}" method="POST" class="mt-3" onsubmit="return confirm('Hapus proyek beserta semua transaksinya?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-trash"></i> Hapus Proyek
                    </button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Alokasi / Top-up</div>
            <div class="card-body">
                <form method="POST" action="{{ route('projects.allocate', $project) }}">
                    @csrf
                    <input type="hidden" name="type" value="allocation">
                    <div class="form-group">
                        <label>Rekening</label>
                        <select name="bank_account_id" class="form-control" required>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                    <button class="btn btn-primary btn-block">Simpan Alokasi</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Tambah Pengeluaran</div>
            <div class="card-body">
                <form method="POST" action="{{ route('projects.expenses.store', $project) }}">
                    @csrf
                    <div class="form-group">
                        <label>Rekening</label>
                        <select name="bank_account_id" class="form-control" required>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategori Pengeluaran</label>
                        <select name="category_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                    <button class="btn btn-danger btn-block">Catat Pengeluaran</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Skema Iuran Proyek</div>
            <div class="card-body">
                <form method="POST" action="{{ route('projects.iuran-plan.update', $project) }}">
                    @csrf
                    <div class="form-group">
                        <label>Target Dana Proyek</label>
                        <input type="number" name="target_amount" class="form-control" min="1" step="0.01"
                            value="{{ old('target_amount', $project->target_amount) }}" required>
                        <small class="text-muted">Sistem akan membagi jatah iuran agar totalnya mencapai target ini.</small>
                    </div>
                    <div class="form-group">
                        <label>Mode Pembagian</label>
                        <select name="iuran_allocation_mode" class="form-control" required>
                            <option value="default" {{ old('iuran_allocation_mode', $project->iuran_allocation_mode) === 'default' ? 'selected' : '' }}>Default (rata)</option>
                            <option value="kelas" {{ old('iuran_allocation_mode', $project->iuran_allocation_mode) === 'kelas' ? 'selected' : '' }}>Kelas (berdasarkan persentase)</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Kelas A (%)</label>
                            <input type="number" name="iuran_class_a_percent" class="form-control" min="1" step="0.01"
                                value="{{ old('iuran_class_a_percent', $project->iuran_class_a_percent ?? 130) }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Kelas B (%)</label>
                            <input type="number" name="iuran_class_b_percent" class="form-control" min="1" step="0.01"
                                value="{{ old('iuran_class_b_percent', $project->iuran_class_b_percent ?? 110) }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Kelas C (%)</label>
                            <input type="number" name="iuran_class_c_percent" class="form-control" min="1" step="0.01"
                                value="{{ old('iuran_class_c_percent', $project->iuran_class_c_percent ?? 100) }}" required>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-block">Simpan Skema & Hitung Ulang</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Tag Anggota & Tugas Petugas Iuran</div>
            <div class="card-body">
                @php
                    $selectedMemberIds = collect(old('iuran_member_ids', old('iuran_member_id') ? [old('iuran_member_id')] : []))
                        ->map(fn ($id) => (string) $id)
                        ->all();
                @endphp
                <form method="POST" action="{{ route('projects.iuran-assignments.store', $project) }}">
                    @csrf
                    <div class="form-group">
                        <label>Cari & Centang Anggota Iuran</label>
                        <input
                            type="text"
                            id="memberSearchInput"
                            class="form-control"
                            placeholder="Cari nama anggota..."
                        >
                        <div class="d-flex justify-content-between mt-2 mb-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="memberSelectAllBtn">
                                Centang Semua Tampil
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="memberClearAllBtn">
                                Batal Semua
                            </button>
                        </div>
                        <div id="memberChecklistBox" class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
                            @foreach($iuranMembers as $member)
                                <div class="form-check member-check-item" data-member-name="{{ strtolower($member->name) }}">
                                    <input
                                        class="form-check-input member-check-input"
                                        type="checkbox"
                                        name="iuran_member_ids[]"
                                        value="{{ $member->id }}"
                                        id="member_checkbox_{{ $member->id }}"
                                        {{ in_array((string) $member->id, $selectedMemberIds, true) ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label" for="member_checkbox_{{ $member->id }}">
                                        {{ $member->name }}
                                    </label>
                                </div>
                            @endforeach
                            <div id="memberSearchEmpty" class="text-muted small" style="display:none;">
                                Tidak ada anggota yang cocok dengan pencarian.
                            </div>
                        </div>
                        <small class="text-muted">Centang beberapa anggota sekaligus sesuai kebutuhan proyek.</small>
                    </div>
                    <div class="form-group">
                        <label>Kelas Iuran Anggota</label>
                        <select name="member_class" class="form-control">
                            <option value="A" {{ old('member_class') === 'A' ? 'selected' : '' }}>Kelas A (porsi tertinggi)</option>
                            <option value="B" {{ old('member_class') === 'B' ? 'selected' : '' }}>Kelas B (menengah)</option>
                            <option value="C" {{ old('member_class', 'C') === 'C' ? 'selected' : '' }}>Kelas C (standar)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Petugas Penarik</label>
                        <select name="officer_user_id" class="form-control" required>
                            <option value="">-- Pilih Petugas --</option>
                            @foreach($iuranOfficers as $officer)
                                <option value="{{ $officer->id }}" {{ (string) old('officer_user_id') === (string) $officer->id ? 'selected' : '' }}>
                                    {{ $officer->name }} ({{ $officer->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <input type="text" name="note" class="form-control" placeholder="Opsional" value="{{ old('note') }}">
                    </div>
                    <button class="btn btn-success btn-block">Simpan Penugasan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header">Daftar Penugasan Iuran Proyek</div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Anggota</th>
                            <th>Kelas</th>
                            <th>Bobot</th>
                            <th>Jatah Iuran</th>
                            <th>Petugas</th>
                            <th>Catatan</th>
                            <th>Ditugaskan Oleh</th>
                            <th width="90">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($iuranAssignments as $assignment)
                            <tr>
                                <td>{{ $assignment->member?->name ?? '-' }}</td>
                                <td>{{ $assignment->member_class ?? 'C' }}</td>
                                <td>{{ number_format((float) $assignment->class_percent, 2, ',', '.') }}%</td>
                                <td>Rp {{ number_format((float) $assignment->planned_amount,0,',','.') }}</td>
                                <td>{{ $assignment->officer?->name ?? '-' }}</td>
                                <td>{{ $assignment->note ?: '-' }}</td>
                                <td>{{ $assignment->assignedBy?->name ?? '-' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('projects.iuran-assignments.destroy', [$project, $assignment]) }}" onsubmit="return confirm('Hapus penugasan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">Belum ada penugasan petugas iuran untuk proyek ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Log Transaksi Proyek</div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Kategori</th>
                            <th>Nominal</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->transactions as $tx)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($tx->date)->format('d-m-Y') }}</td>
                                <td><span class="badge badge-{{ $tx->type === 'expense' ? 'danger' : 'primary' }}">{{ $tx->type }}</span></td>
                                <td>{{ $tx->category?->name ?? '-' }}</td>
                                <td>Rp {{ number_format($tx->amount,0,',','.') }}</td>
                                <td>{{ $tx->note ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada transaksi</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('memberSearchInput');
    const selectAllBtn = document.getElementById('memberSelectAllBtn');
    const clearAllBtn = document.getElementById('memberClearAllBtn');
    const emptyState = document.getElementById('memberSearchEmpty');
    const memberItems = Array.from(document.querySelectorAll('.member-check-item'));

    if (!searchInput || !selectAllBtn || !clearAllBtn || memberItems.length === 0) {
        return;
    }

    const normalize = (value) => (value || '').toString().toLowerCase().trim();

    const applyFilter = () => {
        const query = normalize(searchInput.value);
        let visibleCount = 0;

        memberItems.forEach((item) => {
            const name = normalize(item.getAttribute('data-member-name'));
            const visible = query === '' || name.includes(query);
            item.style.display = visible ? '' : 'none';
            if (visible) {
                visibleCount++;
            }
        });

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? '' : 'none';
        }
    };

    searchInput.addEventListener('input', applyFilter);

    selectAllBtn.addEventListener('click', () => {
        memberItems.forEach((item) => {
            if (item.style.display === 'none') {
                return;
            }
            const checkbox = item.querySelector('.member-check-input');
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    });

    clearAllBtn.addEventListener('click', () => {
        memberItems.forEach((item) => {
            const checkbox = item.querySelector('.member-check-input');
            if (checkbox) {
                checkbox.checked = false;
            }
        });
    });

    applyFilter();
});
</script>
@endpush
