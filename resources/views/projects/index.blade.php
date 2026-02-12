@extends('layouts.app')

@section('title', 'Proyek')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Proyek</h5>
        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Proyek Baru
        </a>
    </div>

    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row">
            @forelse($projects as $project)
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5>{{ $project->name }}</h5>
                                <span class="badge badge-info">{{ ucfirst($project->status) }}</span>
                            </div>
                            <p class="text-muted">{{ $project->description ?? 'Tidak ada deskripsi' }}</p>
                            <div class="mb-2">
                                <small>Rekening: {{ $project->bankAccount?->name ?? '-' }}</small><br>
                                <small>Target: Rp {{ number_format($project->target_amount,0,',','.') }}</small>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $project->progress ?? 0 }}%"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small>Terpakai (net): Rp {{ number_format($project->spent ?? 0,0,',','.') }}</small>
                                <small>{{ $project->progress ?? 0 }}%</small>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-flex">
                                <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-primary btn-sm flex-grow-1 mr-1">Detail</a>
                                <form action="{{ route('projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Hapus proyek ini beserta transaksinya?')" class="mb-0">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted">Belum ada proyek</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
