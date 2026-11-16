@extends('layouts.vertical', ['title' => 'Dokumen RBA', 'subTitle' => $label])

@section('css')
    <style>
        .rba-document-preview { display: block; width: 100%; height: 78vh; min-height: 560px; border: 0; }
        .spm-modal-body { max-height: calc(100vh - 190px); overflow-y: auto; }
        @media (max-width: 575.98px) { .rba-document-preview { min-height: 420px; } }
    </style>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="card-title mb-2">Dokumen {{ $label }}</h4>
                    <p class="text-muted mb-0">{{ auth()->user()->upt }} · Tahun Anggaran {{ \App\Support\TahunRba::selected() }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#spmTargetModal">
                        <i class="ri-edit-line me-1" aria-hidden="true"></i>Input Target SPM
                    </button>
                    @if ($wordAvailable)
                        <a class="btn btn-info" href="{{ route('dokumen-rba.download', ['jenis' => $jenis, 'format' => 'word']) }}">
                            <i class="ri-file-word-2-line me-1" aria-hidden="true"></i> Unduh Word
                        </a>
                    @else
                        <button class="btn btn-info" type="button" disabled>Unduh Word</button>
                    @endif
                    @if ($pdfAvailable)
                        <a class="btn btn-success" href="{{ route('dokumen-rba.download', ['jenis' => $jenis, 'format' => 'pdf']) }}">
                            <i class="ri-file-pdf-2-line me-1" aria-hidden="true"></i> Unduh PDF
                        </a>
                    @else
                        <button class="btn btn-success" type="button" disabled>Unduh PDF</button>
                    @endif
                </div>
            </div>
            @if(session('success')) <div class="alert alert-success mt-3 mb-0">{{ session('success') }}</div> @endif
            @if($errors->any()) <div class="alert alert-danger mt-3 mb-0">Periksa kembali nilai Target Sasaran dan Target Capaian.</div> @endif
        </div>
    </div>

    <div class="modal fade" id="spmTargetModal" tabindex="-1" aria-labelledby="spmTargetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="spmTargetModalLabel">Target Pencapaian SPM</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="{{ route('dokumen-rba.spm.store', ['jenis' => $jenis]) }}">
                    @csrf
                    <div class="modal-body spm-modal-body">
                        <p class="text-muted small">Isi Target Sasaran dan Target Capaian. Indikator SPM mengikuti format Dokumen RBA.</p>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light"><tr><th class="text-center" style="width:6%">No.</th><th>Indikator SPM</th><th style="width:22%">Target Sasaran</th><th style="width:22%">Target Capaian (%)</th></tr></thead>
                                <tbody>
                                @foreach($spmIndicators as $nomor => $spm)
                                    <tr>
                                        <td class="text-center">{{ $nomor }}.</td>
                                        <td>{{ $spm['indikator'] }}</td>
                                        <td><input class="form-control" type="number" min="0" step="0.01" name="target_sasaran[{{ $nomor }}]" value="{{ old('target_sasaran.'.$nomor, $spm['target_sasaran']) }}" required></td>
                                        <td><div class="input-group"><input class="form-control" type="number" min="0" max="100" step="0.01" name="target_capaian[{{ $nomor }}]" value="{{ old('target_capaian.'.$nomor, $spm['target_capaian']) }}" required><span class="input-group-text">%</span></div></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i>Simpan Target SPM</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="card-title mb-0">Preview Dokumen</h5>
            @if ($pdfAvailable)
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('dokumen-rba.preview', ['jenis' => $jenis]) }}" target="_blank" rel="noopener">
                    <i class="ri-external-link-line me-1" aria-hidden="true"></i> Buka Preview
                </a>
            @endif
        </div>
        <div class="card-body p-0">
            @if ($pdfAvailable)
                <iframe class="rba-document-preview" title="Preview Dokumen {{ $label }}"
                    src="{{ route('dokumen-rba.preview', ['jenis' => $jenis]) }}#view=FitH">
                </iframe>
                <p class="small text-muted px-3 py-2 mb-0">Jika preview tidak tampil, pilih Buka Preview atau Unduh PDF.</p>
            @else
                <div class="text-center text-muted p-5">Template PDF belum tersedia. Preview akan ditampilkan setelah template tersedia.</div>
            @endif
        </div>
    </div>
@endsection
