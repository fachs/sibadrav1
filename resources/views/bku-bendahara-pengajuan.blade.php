@extends('layouts.vertical', ['title' => 'Pengajuan Perubahan BKU', 'subTitle' => $verifikator === \App\Models\BkuBendaharaPerubahanRequest::KEPALA_PUSKESMAS ? 'Kepala Puskesmas' : 'Dinas Kesehatan'])

@section('content')
<div class="card">
    <div class="card-body">
        <h5 class="mb-3">Pengajuan Perubahan BKU Bendahara Pengeluaran</h5>
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr><th>Unit BLUD</th><th>Tanggal Transaksi</th><th>Transaksi</th><th>Aksi</th><th>Alasan</th><th>Status</th><th>Verifikasi</th></tr>
                </thead>
                <tbody>
                    @forelse($pengajuan as $item)
                        @php $tanggalTransaksi = $item->aksi === 'rincian' ? ($item->spdLs?->tanggal_dba ?? $item->spdLs?->created_at) : $item->bku?->tanggal; @endphp
                        <tr>
                            <td>{{ $item->unitBlud?->nama ?? '-' }}</td>
                            <td>{{ $tanggalTransaksi ? \Carbon\Carbon::parse($tanggalTransaksi)->format('d-m-Y') : '-' }}</td>
                            <td>
                                @if($item->aksi === 'rincian')
                                    {{ $item->spdLs?->nomor ?: '-' }} — SPD LS
                                @else
                                    {{ $item->bku?->no_transaksi ?: '-' }} — {{ $item->bku?->uraian ?: 'Transaksi tidak tersedia' }}
                                @endif
                            </td>
                            <td>{{ ['edit' => 'Edit', 'hapus' => 'Hapus', 'rincian' => 'Tambah Rincian'][$item->aksi] ?? $item->aksi }}</td>
                            <td>{{ $item->alasan }}</td>
                            <td>{{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'used' => 'Sudah digunakan'][$item->status] ?? $item->status }}</td>
                            <td>
                                @if($item->status === 'pending')
                                    <div class="d-flex gap-1">
                                        @foreach(['approved' => 'Setujui', 'rejected' => 'Tolak'] as $decision => $label)
                                            <form method="POST" action="{{ route('bku-bendahara-pengajuan.review', $item->id) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="keputusan" value="{{ $decision }}">
                                                <button class="btn btn-sm {{ $decision === 'approved' ? 'btn-success' : 'btn-danger' }}" type="submit">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">Belum ada pengajuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
