@extends('layouts.vertical', ['title' => 'Dokumen RBA', 'subTitle' => $label])

@section('css')
    <style>
        .spm-modal-body { max-height: calc(100vh - 190px); overflow-y: auto; }
        .docx-preview-viewport {
            position: relative;
            width: 100%;
            height: 80vh;
            min-height: 580px;
            overflow: auto;
            background: #e9ecef;
            border-bottom-left-radius: inherit;
            border-bottom-right-radius: inherit;
        }
        .docx-preview-stage {
            transform-origin: top center;
            transition: transform .15s ease;
            padding: 16px 0 32px;
        }
        .docx-preview-viewport .docx-rba-wrapper {
            background: transparent !important;
            padding: 12px 16px !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
        }
        .docx-preview-viewport section.docx-rba {
            background: #ffffff !important;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .14) !important;
            margin-bottom: 0 !important;
        }
        .docx-preview-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 460px;
            gap: 12px;
            color: #475569;
        }
        @media (max-width: 575.98px) {
            .docx-preview-viewport { min-height: 420px; }
        }
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
                        <button class="btn btn-outline-secondary" type="button" disabled title="Konversi PDF membutuhkan LibreOffice di server hosting">
                            <i class="ri-file-pdf-2-line me-1" aria-hidden="true"></i> Unduh PDF
                        </button>
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
            <div class="d-flex align-items-center gap-2">
                <h5 class="card-title mb-0">Preview Dokumen Word</h5>
                <span class="badge bg-info-subtle text-info">DOCX Live Preview</span>
            </div>
            @if ($wordAvailable)
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Zoom Preview">
                        <button type="button" class="btn btn-outline-secondary" id="docx-zoom-out" title="Perkecil"><i class="ri-zoom-out-line"></i></button>
                        <button type="button" class="btn btn-outline-secondary" id="docx-zoom-reset" title="Reset Zoom"><span id="docx-zoom-label">100%</span></button>
                        <button type="button" class="btn btn-outline-secondary" id="docx-zoom-in" title="Perbesar"><i class="ri-zoom-in-line"></i></button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="docx-print-btn" disabled title="Cetak atau Simpan sebagai PDF lewat Browser">
                        <i class="ri-printer-line me-1" aria-hidden="true"></i> Cetak / Simpan PDF
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="docx-reload-btn" title="Muat Ulang Preview">
                        <i class="ri-refresh-line me-1" aria-hidden="true"></i> Muat Ulang
                    </button>
                </div>
            @endif
        </div>
        <div class="card-body p-0">
            @if ($wordAvailable)
                <div class="docx-preview-viewport" id="docx-preview-viewport">
                    <div class="docx-preview-loading" id="docx-preview-loading">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="fw-semibold">Memuat pratinjau dokumen Word (.docx)...</div>
                        <div class="small text-muted">Menyiapkan data RBA dan merender halaman di browser Anda.</div>
                    </div>
                    <div class="docx-preview-stage d-none" id="docx-preview-stage">
                        <div id="docx-preview-container"></div>
                    </div>
                    <div class="text-center p-5 d-none" id="docx-preview-error">
                        <div class="avatar-lg bg-danger-subtle rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <i class="ri-error-warning-line text-danger fs-24"></i>
                        </div>
                        <h5 class="text-dark mb-2">Gagal Memuat Pratinjau Word</h5>
                        <p class="text-muted mx-auto mb-3" id="docx-preview-error-msg" style="max-width: 560px;">
                            Terjadi kendala saat memuat dokumen Word. Anda tetap dapat mengunduh dokumen langsung dalam format Word (.docx).
                        </p>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.loadRbaDocxPreview && window.loadRbaDocxPreview()">
                                <i class="ri-refresh-line me-1"></i> Coba Lagi
                            </button>
                            <a class="btn btn-info" href="{{ route('dokumen-rba.download', ['jenis' => $jenis, 'format' => 'word']) }}">
                                <i class="ri-file-word-2-line me-1"></i> Unduh Word (.docx)
                            </a>
                        </div>
                    </div>
                </div>
                <p class="small text-muted px-3 py-2 mb-0">
                    Pratinjau dirender langsung dari file Word (<code>.docx</code>) di browser. Untuk hasil cetak/edit resmi 100% presisi, gunakan tombol <strong>Unduh Word</strong> atau <strong>Cetak / Simpan PDF</strong>.
                </p>
            @else
                <div class="text-center text-muted p-5">Template dokumen belum tersedia. Preview akan ditampilkan setelah template tersedia.</div>
            @endif
        </div>
    </div>
@endsection

@if ($wordAvailable)
@section('script')
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.3/dist/docx-preview.min.js"></script>
    <script>
        (function () {
            var docxUrl = @json(route('dokumen-rba.download', ['jenis' => $jenis, 'format' => 'word']));
            var loadingEl = document.getElementById('docx-preview-loading');
            var stageEl = document.getElementById('docx-preview-stage');
            var containerEl = document.getElementById('docx-preview-container');
            var errorEl = document.getElementById('docx-preview-error');
            var errorMsgEl = document.getElementById('docx-preview-error-msg');
            var printBtn = document.getElementById('docx-print-btn');
            var reloadBtn = document.getElementById('docx-reload-btn');
            var zoomOutBtn = document.getElementById('docx-zoom-out');
            var zoomInBtn = document.getElementById('docx-zoom-in');
            var zoomResetBtn = document.getElementById('docx-zoom-reset');
            var zoomLabelEl = document.getElementById('docx-zoom-label');

            var currentZoom = 1;

            function applyZoom(scale) {
                currentZoom = Math.max(0.5, Math.min(1.5, Math.round(scale * 10) / 10));
                if (stageEl) {
                    stageEl.style.transform = 'scale(' + currentZoom + ')';
                }
                if (zoomLabelEl) {
                    zoomLabelEl.textContent = Math.round(currentZoom * 100) + '%';
                }
            }

            if (zoomOutBtn) zoomOutBtn.addEventListener('click', function () { applyZoom(currentZoom - 0.1); });
            if (zoomInBtn) zoomInBtn.addEventListener('click', function () { applyZoom(currentZoom + 0.1); });
            if (zoomResetBtn) zoomResetBtn.addEventListener('click', function () { applyZoom(1); });

            function showError(message) {
                if (loadingEl) loadingEl.classList.add('d-none');
                if (stageEl) stageEl.classList.add('d-none');
                if (errorEl) errorEl.classList.remove('d-none');
                if (errorMsgEl && message) errorMsgEl.textContent = message;
                if (printBtn) printBtn.disabled = true;
            }

            window.loadRbaDocxPreview = function () {
                if (typeof window.docx === 'undefined' || typeof window.JSZip === 'undefined') {
                    showError('Library pratinjau Word gagal dimuat dari CDN. Periksa koneksi internet Anda atau klik Unduh Word.');
                    return;
                }

                if (loadingEl) loadingEl.classList.remove('d-none');
                if (stageEl) stageEl.classList.add('d-none');
                if (errorEl) errorEl.classList.add('d-none');
                if (printBtn) printBtn.disabled = true;
                if (containerEl) containerEl.innerHTML = '';

                fetch(docxUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Server mengembalikan status ' + response.status + '.');
                    }
                    return response.blob();
                })
                .then(function (blob) {
                    return window.docx.renderAsync(blob, containerEl, null, {
                        className: 'docx-rba',
                        inWrapper: true,
                        ignoreWidth: false,
                        ignoreHeight: false,
                        ignoreFonts: false,
                        breakPages: true,
                        ignoreLastRenderedPageBreak: false,
                        experimental: true,
                        useBase64URL: true,
                        renderHeaders: true,
                        renderFooters: true,
                        renderFootnotes: true,
                        renderEndnotes: true
                    });
                })
                .then(function () {
                    if (loadingEl) loadingEl.classList.add('d-none');
                    if (stageEl) stageEl.classList.remove('d-none');
                    if (printBtn) printBtn.disabled = false;
                })
                .catch(function (err) {
                    showError('Gagal merender pratinjau dokumen Word: ' + (err && err.message ? err.message : 'Kesalahan tidak diketahui.'));
                });
            };

            if (reloadBtn) {
                reloadBtn.addEventListener('click', function () {
                    window.loadRbaDocxPreview();
                });
            }

            if (printBtn) {
                printBtn.addEventListener('click', function () {
                    var printWin = window.open('', '_blank');
                    if (!printWin) {
                        alert('Pop-up diblokir oleh browser. Izinkan pop-up untuk mencetak dokumen.');
                        return;
                    }
                    var styles = '';
                    containerEl.querySelectorAll('style').forEach(function (s) {
                        styles += s.outerHTML;
                    });
                    printWin.document.open();
                    printWin.document.write(
                        '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Dokumen {{ $label }}</title>' +
                        styles +
                        '<style>@media print { body { margin: 0; background: #fff; } .docx-rba-wrapper { background: #fff !important; padding: 0 !important; } section.docx-rba { box-shadow: none !important; margin: 0 auto !important; page-break-after: always; } }</style>' +
                        '</head><body>' + containerEl.innerHTML + '</body></html>'
                    );
                    printWin.document.close();
                    printWin.focus();
                    setTimeout(function () { printWin.print(); }, 400);
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                window.loadRbaDocxPreview();
            });
        })();
    </script>
@endsection
@endif
