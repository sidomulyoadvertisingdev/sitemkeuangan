@extends('layouts.app')

@section('title', 'Log Aktivitas')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="form-row align-items-end">
            <div class="form-group col-md-4">
                <label>Cari Aktivitas / User</label>
                <input
                    type="text"
                    name="q"
                    class="form-control"
                    value="{{ $q }}"
                    placeholder="Contoh: menambahkan pemasukan"
                >
            </div>
            <div class="form-group col-md-3">
                <label>Aksi</label>
                <select name="action" class="form-control">
                    <option value="">Semua</option>
                    <option value="created" {{ $action === 'created' ? 'selected' : '' }}>Created</option>
                    <option value="updated" {{ $action === 'updated' ? 'selected' : '' }}>Updated</option>
                    <option value="deleted" {{ $action === 'deleted' ? 'selected' : '' }}>Deleted</option>
                </select>
            </div>
            <div class="form-group col-md-5 d-flex">
                <button class="btn btn-primary mr-2">Filter</button>
                <a href="{{ route('activity-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Semua Log Aktivitas</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th width="180">Tanggal & Jam</th>
                        <th width="180">User</th>
                        <th width="100">Aksi</th>
                        <th>Aktivitas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $activity)
                        @php
                            $badgeClass = match($activity->action) {
                                'created' => 'badge-success',
                                'updated' => 'badge-warning',
                                'deleted' => 'badge-danger',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <tr>
                            <td>{{ $activity->created_at?->format('d-m-Y H:i:s') }}</td>
                            <td>{{ $activity->actor?->name ?? 'Sistem' }}</td>
                            <td><span class="badge {{ $badgeClass }}">{{ strtoupper($activity->action) }}</span></td>
                            <td>{{ $activity->description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Belum ada log aktivitas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection

