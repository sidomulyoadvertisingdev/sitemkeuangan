@extends('layouts.app')

@section('title','Permintaan Topup')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Permintaan Topup Manual</h5>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        <h6>Menunggu Verifikasi</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th>Nominal</th>
                        <th>Kode Unik</th>
                        <th>Total Transfer</th>
                        <th>Bukti</th>
                        <th>Catatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pending as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->requesterMember?->name }}<br>
                                <small>{{ $row->requesterMember?->member_no }}</small></td>
                            <td>Rp {{ number_format($row->amount,0,',','.') }}</td>
                            <td>{{ $row->unique_code }}</td>
                            <td>Rp {{ number_format($row->pay_amount,0,',','.') }}</td>
                            <td>
                                @if($row->proof_path)
                                    <a href="{{ asset('storage/'.$row->proof_path) }}" target="_blank">Lihat Bukti</a>
                                @else
                                    <span class="text-muted">Belum ada</span>
                                @endif
                            </td>
                            <td>{{ $row->note }}</td>
                            <td class="d-flex gap-2 flex-wrap">
                                <form method="POST" action="{{ route('koperasi.topups.approve', $row) }}">
                                    @csrf
                                    <input type="hidden" name="note" value="{{ $row->note }}">
                                    <button class="btn btn-sm btn-success" onclick="return confirm('Setujui topup ini?')">
                                        Approve
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('koperasi.topups.cancel', $row) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Tolak / batalkan topup ini?')">
                                        Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">Tidak ada topup pending.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h6 class="mt-4">Riwayat (50 terakhir)</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th>Nominal</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Diproses</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->requesterMember?->name }}</td>
                            <td>Rp {{ number_format($row->amount,0,',','.') }}</td>
                            <td>Rp {{ number_format($row->pay_amount,0,',','.') }}</td>
                            <td>
                                @if($row->status === 'completed')
                                    <span class="badge bg-success">Disetujui</span>
                                @elseif($row->status === 'rejected')
                                    <span class="badge bg-danger">Dibatalkan</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($row->status) }}</span>
                                @endif
                            </td>
                            <td>{{ $row->approved_at ? $row->approved_at->format('Y-m-d H:i') : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Belum ada riwayat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
