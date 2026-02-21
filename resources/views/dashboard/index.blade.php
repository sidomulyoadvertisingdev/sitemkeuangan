@extends('layouts.app')

@section('title','Dashboard')

@section('content')

{{-- ================= INFO BOX ================= --}}
<div class="row">

    <div class="col-lg-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#22c55e33,#22c55e55);">
            <div class="inner">
                <h3>Rp {{ number_format($saldo,0,',','.') }}</h3>
                <p>Saldo</p>
            </div>
            <div class="icon">
                <i class="fas fa-wallet"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#2563eb33,#2563eb55);">
            <div class="inner">
                <h3>Rp {{ number_format($income,0,',','.') }}</h3>
                <p>Total Pemasukan</p>
            </div>
            <div class="icon">
                <i class="fas fa-arrow-down"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#ef444433,#ef444455);">
            <div class="inner">
                <h3>Rp {{ number_format($expense,0,',','.') }}</h3>
                <p>Total Pengeluaran</p>
            </div>
            <div class="icon">
                <i class="fas fa-arrow-up"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#f59e0b33,#f59e0b55);">
            <div class="inner">
                <h3>Rp {{ number_format($hutang,0,',','.') }}</h3>
                <p>Total Hutang</p>
            </div>
            <div class="icon">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#06b6d433,#06b6d455);">
            <div class="inner">
                <h3>Rp {{ number_format($piutang,0,',','.') }}</h3>
                <p>Total Piutang</p>
            </div>
            <div class="icon">
                <i class="fas fa-hand-holding"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box" style="background: linear-gradient(135deg,#14b8a633,#14b8a655);">
            <div class="inner">
                <h3>Rp {{ number_format($iuranCollectedMonth,0,',','.') }}</h3>
                <p>Perolehan Iuran Bulan Ini</p>
                <small>Total: Rp {{ number_format($iuranCollected,0,',','.') }} ({{ $iuranProgress }}%)</small>
            </div>
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>

</div>

{{-- ================= REMINDER BUDGET ================= --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Reminder Budget Bulan Ini
                </h3>
            </div>

            <div class="card-body p-0">
                @if($budgets->isEmpty())
                    <p class="text-center text-muted p-3 mb-0">
                        Belum ada budget untuk bulan ini
                    </p>
                @else
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Limit</th>
                            <th>Terpakai</th>
                            <th>Sisa</th>
                            <th width="25%">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($budgets as $b)
                            @php
                                if ($b->percent >= 100) {
                                    $bar = 'bg-danger';
                                } elseif ($b->percent >= 80) {
                                    $bar = 'bg-warning';
                                } else {
                                    $bar = 'bg-success';
                                }
                            @endphp
                            <tr>
                                <td>{{ $b->category->name }}</td>
                                <td>Rp {{ number_format($b->limit,0,',','.') }}</td>
                                <td>Rp {{ number_format($b->used,0,',','.') }}</td>
                                <td class="{{ $b->remaining <= 0 ? 'text-danger font-weight-bold' : '' }}">
                                    Rp {{ number_format($b->remaining,0,',','.') }}
                                </td>
                                <td>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar {{ $bar }}"
                                             style="width: {{ min($b->percent,100) }}%">
                                            {{ $b->percent }}%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ================= PEROLEHAN IURAN ================= --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users mr-1"></i>
                    Perolehan Iuran
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6 mb-2">
                        <strong>Target Iuran</strong>
                        <div>Rp {{ number_format($iuranTarget,0,',','.') }}</div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <strong>Tercapai</strong>
                        <div>Rp {{ number_format($iuranCollected,0,',','.') }}</div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <strong>Sisa</strong>
                        <div>Rp {{ number_format($iuranRemaining,0,',','.') }}</div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <strong>Progress</strong>
                        <div>{{ $iuranProgress }}%</div>
                    </div>
                </div>

                <div class="progress progress-sm mt-2">
                    <div class="progress-bar bg-info" style="width: {{ $iuranProgress }}%">
                        {{ $iuranProgress }}%
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Nama Anggota</th>
                                <th>Target</th>
                                <th>Terbayar</th>
                                <th>Sisa</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($iuranMembers->isNotEmpty())
                                <tr>
                                    <td colspan="5" class="text-center font-weight-bold bg-light">
                                        Daftar Anggota Iuran Lunas
                                    </td>
                                </tr>
                            @endif
                            @forelse($iuranMembers as $member)
                                <tr>
                                    <td>{{ $member->name }}</td>
                                    <td>Rp {{ number_format($member->target_amount,0,',','.') }}</td>
                                    <td>Rp {{ number_format($member->paid_amount,0,',','.') }}</td>
                                    <td>Rp {{ number_format($member->remaining_amount,0,',','.') }}</td>
                                    <td>
                                        <span class="badge {{ $member->is_completed ? 'badge-success' : 'badge-warning' }}">
                                            {{ $member->is_completed ? 'Lunas' : 'Belum Lunas' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada anggota yang lunas</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <hr class="my-3">
                <h6 class="mb-2">Status Anggota Iuran</h6>
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <canvas id="iuranStatusChart" height="180"></canvas>
                    </div>
                    <div class="col-md-8">
                        <div class="d-flex flex-wrap">
                            <div class="mr-4 mb-2">
                                <span class="badge badge-success">Lunas</span>
                                <div><strong>{{ $iuranLunasCount }}</strong> anggota ({{ $iuranLunasPercent }}%)</div>
                            </div>
                            <div class="mr-4 mb-2">
                                <span class="badge badge-warning">Belum Lunas</span>
                                <div><strong>{{ $iuranBelumLunasCount }}</strong> anggota ({{ $iuranBelumLunasPercent }}%)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= GRAFIK ================= --}}
<div class="row">

    {{-- GRAFIK PEMASUKAN VS PENGELUARAN --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Pemasukan vs Pengeluaran ({{ date('Y') }})
                </h3>
            </div>
            <div class="card-body">
                <canvas id="incomeExpenseChart" height="120"></canvas>
            </div>
        </div>
    </div>

    {{-- GRAFIK PENGELUARAN PER KATEGORI --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Pengeluaran per Kategori (Bulan Ini)
                </h3>
            </div>
            <div class="card-body">
                <canvas id="categoryChart"></canvas>

                @if($categoryExpense->isEmpty())
                    <p class="text-center text-muted mt-3">
                        Belum ada data pengeluaran
                    </p>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ================= PROYEK & HUTANG PROGRESS ================= --}}
<div class="row mt-4">

    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Progres Proyek</h3>
                <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-primary">Lihat semua</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Proyek</th>
                            <th>Target</th>
                            <th>Terpakai</th>
                            <th width="30%">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $p)
                        <tr>
                            <td>{{ $p->name }}</td>
                            <td>Rp {{ number_format($p->target_amount,0,',','.') }}</td>
                            <td>Rp {{ number_format($p->spent,0,',','.') }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar" style="width: {{ $p->progress }}%"></div>
                                    </div>
                                    <small class="ml-2">{{ $p->progress }}%</small>
                                </div>
                            </td>
                        </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Belum ada proyek</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Progress Hutang</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $debts = \App\Models\Debt::with('installments')
                                ->where('user_id', auth()->id())
                                ->where('type','hutang')
                                ->get();
                        @endphp
                        @forelse($debts as $d)
                            @php
                                $paid = $d->installments->sum('amount');
                                $remaining = max(0, $d->amount - $paid);
                            @endphp
                            <tr>
                                <td>{{ $d->name }}</td>
                                <td>Rp {{ number_format($remaining,0,',','.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">Tidak ada hutang</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // ================= LINE CHART =================
    new Chart(document.getElementById('incomeExpenseChart'), {
        type: 'line',
        data: {
            labels: @json($months),
            datasets: [
                {
                    label: 'Pemasukan',
                    data: @json($incomes),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40,167,69,0.15)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Pengeluaran',
                    data: @json($expenses),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220,53,69,0.15)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // ================= DOUGHNUT CHART =================
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: @json($categoryExpense->pluck('category')),
            datasets: [{
                data: @json($categoryExpense->pluck('total')),
                backgroundColor: [
                    '#f472b6','#60a5fa','#34d399',
                    '#fcd34d','#c084fc','#38bdf8','#f87171','#a3e635'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: {
                    label: function(ctx){ return ctx.label + ': Rp ' + ctx.parsed.toLocaleString('id-ID'); }
                }}
            }
        }
    });

    // ================= DONUT STATUS IURAN =================
    new Chart(document.getElementById('iuranStatusChart'), {
        type: 'doughnut',
        data: {
            labels: [
                'Lunas ({{ $iuranLunasCount }} | {{ $iuranLunasPercent }}%)',
                'Belum Lunas ({{ $iuranBelumLunasCount }} | {{ $iuranBelumLunasPercent }}%)'
            ],
            datasets: [{
                data: [{{ $iuranLunasCount }}, {{ $iuranBelumLunasCount }}],
                backgroundColor: ['#22c55e', '#f59e0b']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>
@endpush
