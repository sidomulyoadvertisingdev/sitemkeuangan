@extends('layouts.app')

@section('title', 'Dashboard Koperasi')

@section('content')
@php
    $formatCompactCurrency = function ($amount): string {
        $amount = (float) $amount;
        $abs = abs($amount);

        if ($abs >= 1000000000) {
            return 'Rp ' . number_format($amount / 1000000000, 1, ',', '.') . ' M';
        }
        if ($abs >= 1000000) {
            return 'Rp ' . number_format($amount / 1000000, 1, ',', '.') . ' Jt';
        }
        if ($abs >= 1000) {
            return 'Rp ' . number_format($amount / 1000, 1, ',', '.') . ' Rb';
        }

        return 'Rp ' . number_format($amount, 0, ',', '.');
    };

    $netPositive = $summary['net_cashflow_ytd'] >= 0;
    $metrics = [
        [
            'tone' => 'tone-blue',
            'label' => 'Total Member',
            'value' => number_format($summary['total_members'], 0, ',', '.'),
            'sub' => 'Aktif: ' . number_format($summary['active_members'], 0, ',', '.'),
            'icon' => 'fas fa-users',
            'full' => number_format($summary['total_members'], 0, ',', '.'),
        ],
        [
            'tone' => 'tone-cyan',
            'label' => 'Total Simpanan',
            'value' => $formatCompactCurrency($summary['total_savings']),
            'sub' => 'Total seluruh simpanan anggota',
            'icon' => 'fas fa-piggy-bank',
            'full' => 'Rp ' . number_format($summary['total_savings'], 0, ',', '.'),
        ],
        [
            'tone' => 'tone-amber',
            'label' => 'Sisa Pinjaman',
            'value' => $formatCompactCurrency($summary['total_loan_outstanding']),
            'sub' => 'Outstanding pinjaman berjalan',
            'icon' => 'fas fa-hourglass-half',
            'full' => 'Rp ' . number_format($summary['total_loan_outstanding'], 0, ',', '.'),
        ],
        [
            'tone' => 'tone-rose',
            'label' => 'Tunggakan Kurang Bayar',
            'value' => $formatCompactCurrency($summary['total_shortfall']),
            'sub' => 'Akumulasi shortfall cicilan',
            'icon' => 'fas fa-exclamation-triangle',
            'full' => 'Rp ' . number_format($summary['total_shortfall'], 0, ',', '.'),
        ],
        [
            'tone' => 'tone-emerald',
            'label' => 'Rata-rata Tepat Waktu',
            'value' => number_format($summary['avg_on_time_rate'], 1, ',', '.') . '%',
            'sub' => 'Member berhistori: ' . number_format($summary['members_with_installments'], 0, ',', '.'),
            'icon' => 'fas fa-stopwatch',
            'full' => number_format($summary['avg_on_time_rate'], 1, ',', '.') . '%',
        ],
        [
            'tone' => $netPositive ? 'tone-green' : 'tone-red',
            'label' => $netPositive ? 'Cashflow Bersih (+)' : 'Cashflow Bersih (-)',
            'value' => $formatCompactCurrency(abs($summary['net_cashflow_ytd'])),
            'sub' => 'Tahun ' . $summary['year'],
            'icon' => 'fas fa-chart-line',
            'full' => 'Rp ' . number_format(abs($summary['net_cashflow_ytd']), 0, ',', '.'),
        ],
    ];
@endphp

<div class="koperasi-metrics mb-3">
    @foreach($metrics as $metric)
        <article class="koperasi-metric-card {{ $metric['tone'] }}">
            <div class="koperasi-metric-head">
                <div class="koperasi-metric-label">{{ $metric['label'] }}</div>
                <div class="koperasi-metric-icon"><i class="{{ $metric['icon'] }}"></i></div>
            </div>
            <div class="koperasi-metric-value" title="{{ $metric['full'] }}">{{ $metric['value'] }}</div>
            <div class="koperasi-metric-sub">{{ $metric['sub'] }}</div>
        </article>
    @endforeach
</div>

<div class="koperasi-actions mb-4">
    <a href="{{ route('koperasi.index') }}" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-users mr-1"></i> Data Member
    </a>
    <a href="{{ route('koperasi.create') }}" class="btn btn-sm btn-outline-success">
        <i class="fas fa-user-plus mr-1"></i> Tambah Member
    </a>
    <a href="{{ route('koperasi.transactions', ['menu' => 'angsuran']) }}" class="btn btn-sm btn-outline-info">
        <i class="fas fa-exchange-alt mr-1"></i> Transaction
    </a>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card section-card card-outline card-primary h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fas fa-chess-king mr-1"></i>
                    Insight Keputusan Ketua Koperasi
                </h3>
            </div>
            <div class="card-body">
                @forelse($insights as $insight)
                    <div class="mb-2">
                        <i class="fas fa-circle-notch text-primary mr-1"></i>{{ $insight }}
                    </div>
                @empty
                    <div class="text-muted">Belum ada insight.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card section-card card-outline card-success h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Ringkasan Disiplin Anggota</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Tidak Pernah Terlambat</span>
                    <strong>{{ $summary['never_late_count'] }} member</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Selalu Tepat Nominal</span>
                    <strong>{{ $summary['always_exact_count'] }} member</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Pernah Terlambat</span>
                    <strong>{{ $summary['late_member_count'] }} member</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Pernah Kurang Bayar</span>
                    <strong>{{ $summary['shortfall_member_count'] }} member</strong>
                </div>
                <hr>
                <div class="small text-muted">
                    Anggota yang tidak pernah terlambat dan selalu tepat nominal menjadi kandidat prioritas untuk kenaikan plafon pinjaman.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card section-card">
            <div class="card-header">
                <h3 class="card-title">Tren Kas Koperasi {{ $summary['year'] }}</h3>
            </div>
            <div class="card-body">
                <canvas id="koperasiCashflowChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card section-card mb-3">
            <div class="card-header">
                <h3 class="card-title">Komposisi Ketepatan Waktu</h3>
            </div>
            <div class="card-body">
                <canvas id="punctualityChart" height="160"></canvas>
            </div>
        </div>
        <div class="card section-card">
            <div class="card-header">
                <h3 class="card-title">Kesesuaian Nominal</h3>
            </div>
            <div class="card-body">
                <canvas id="paymentQualityChart" height="160"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card section-card">
            <div class="card-header">
                <h3 class="card-title">Anggota Paling Disiplin</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>No Rekening</th>
                            <th>Nama</th>
                            <th>Riwayat Cicilan</th>
                            <th>Tepat Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topDisciplineMembers as $member)
                            <tr>
                                <td>{{ $member['member_no'] }}</td>
                                <td>{{ $member['name'] }}</td>
                                <td>{{ $member['installment_count'] }}x</td>
                                <td><span class="badge badge-success">{{ $member['on_time_rate'] }}%</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Belum ada data disiplin cicilan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card section-card">
            <div class="card-header">
                <h3 class="card-title">Anggota Perlu Perhatian</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Terlambat</th>
                            <th>Kurang Bayar</th>
                            <th>Sisa Pinjaman</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topRiskMembers as $member)
                            <tr>
                                <td>{{ $member['name'] }}</td>
                                <td><span class="badge badge-warning">{{ $member['late_count'] }}x</span></td>
                                <td>Rp {{ number_format($member['shortfall_total'],0,',','.') }}</td>
                                <td>Rp {{ number_format($member['loan_outstanding'],0,',','.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Tidak ada anggota berisiko saat ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .koperasi-metrics {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .koperasi-metric-card {
        border-radius: 14px;
        padding: 0.9rem 1rem;
        min-height: 150px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.25);
    }

    .koperasi-metric-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.65rem;
        margin-bottom: 0.4rem;
    }

    .koperasi-metric-label {
        font-size: 0.78rem;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #475569;
        font-weight: 700;
        line-height: 1.2;
    }

    .koperasi-metric-icon {
        font-size: 1.6rem;
        opacity: 0.35;
        line-height: 1;
        margin-top: 0.15rem;
    }

    .koperasi-metric-value {
        font-size: clamp(1.25rem, 1.3vw, 1.9rem);
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
        margin-bottom: 0.4rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .koperasi-metric-sub {
        color: #334155;
        font-size: 0.84rem;
        line-height: 1.3;
    }

    .tone-blue { background: linear-gradient(145deg, #dbeafe 0%, #c7d2fe 100%); }
    .tone-cyan { background: linear-gradient(145deg, #dff4ff 0%, #bae6fd 100%); }
    .tone-amber { background: linear-gradient(145deg, #fef3c7 0%, #fde68a 100%); }
    .tone-rose { background: linear-gradient(145deg, #ffe4e6 0%, #fecdd3 100%); }
    .tone-emerald { background: linear-gradient(145deg, #dcfce7 0%, #bbf7d0 100%); }
    .tone-green { background: linear-gradient(145deg, #dcfce7 0%, #a7f3d0 100%); }
    .tone-red { background: linear-gradient(145deg, #fee2e2 0%, #fecaca 100%); }

    .koperasi-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
    }

    .section-card .card-header {
        border-bottom: 1px solid rgba(148, 163, 184, 0.25);
        background: rgba(248, 250, 252, 0.65);
    }

    @media (max-width: 1399.98px) {
        .koperasi-metrics {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .koperasi-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.7rem;
        }

        .koperasi-metric-card {
            min-height: 132px;
            padding: 0.75rem 0.8rem;
        }

        .koperasi-metric-value {
            font-size: 1.15rem;
        }
    }

    @media (max-width: 479.98px) {
        .koperasi-metrics {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const months = @json($chart['months']);
    const incomeSeries = @json($chart['income']);
    const expenseSeries = @json($chart['expense']);
    const netSeries = @json($chart['net']);

    new Chart(document.getElementById('koperasiCashflowChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Pemasukan (Simpanan + Angsuran)',
                    data: incomeSeries,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,0.12)',
                    fill: true,
                    tension: 0.35
                },
                {
                    label: 'Pengeluaran (Pencairan Pinjaman)',
                    data: expenseSeries,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220,38,38,0.12)',
                    fill: true,
                    tension: 0.35
                },
                {
                    label: 'Cashflow Bersih',
                    data: netSeries,
                    borderColor: '#1d4ed8',
                    backgroundColor: 'rgba(29,78,216,0.1)',
                    fill: false,
                    tension: 0.35
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('punctualityChart'), {
        type: 'doughnut',
        data: {
            labels: ['Tidak Pernah Terlambat', 'Pernah Terlambat', 'Belum Ada Riwayat'],
            datasets: [{
                data: @json($chart['punctual_member']),
                backgroundColor: ['#16a34a', '#f59e0b', '#94a3b8']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('paymentQualityChart'), {
        type: 'doughnut',
        data: {
            labels: ['Selalu Tepat Nominal', 'Pernah Kurang Bayar', 'Belum Ada Riwayat'],
            datasets: [{
                data: @json($chart['payment_quality_member']),
                backgroundColor: ['#0284c7', '#dc2626', '#94a3b8']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
</script>
@endpush
