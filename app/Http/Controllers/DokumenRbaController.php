<?php

namespace App\Http\Controllers;

use App\Models\SpmTarget;
use App\Services\RbaDocumentGenerator;
use App\Services\RbaDocumentPdfConverter;
use App\Support\TahunRba;
use Illuminate\Http\Request;

class DokumenRbaController extends Controller
{
    public function show(string $jenis)
    {
        $template = $this->template($jenis);
        $tahun = TahunRba::selected();
        $upt = trim((string) auth()->user()->upt);
        $savedSpm = SpmTarget::where('nama_blud', $upt)->where('tahun_anggaran', $tahun)
            ->orderBy('nomor')->get()->keyBy('nomor');
        $spmIndicators = collect(config('spm.indicators', []))->mapWithKeys(function ($indicator, $index) use ($savedSpm) {
            $nomor = $index + 1;
            $saved = $savedSpm->get($nomor);
            return [$nomor => [
                'indikator' => $indicator,
                'target_sasaran' => $saved?->target_sasaran ?? 0,
                'target_capaian' => $saved?->target_capaian ?? 0,
            ]];
        });

        $pdfConverterAvailable = app(RbaDocumentPdfConverter::class)->isAvailable();

        return view('dokumen-rba', [
            'jenis' => $jenis,
            'label' => $template['label'],
            'wordAvailable' => is_file($template['word']),
            'pdfAvailable' => is_file($template['word']) && $pdfConverterAvailable,
            'pdfConverterAvailable' => $pdfConverterAvailable,
            'tahun' => $tahun,
            'spmIndicators' => $spmIndicators,
        ]);
    }

    public function storeSpm(Request $request, string $jenis)
    {
        $this->template($jenis);
        $indicators = config('spm.indicators', []);
        $request->validate([
            'target_sasaran' => ['required', 'array', 'size:'.count($indicators)],
            'target_sasaran.*' => ['required', 'numeric', 'min:0'],
            'target_capaian' => ['required', 'array', 'size:'.count($indicators)],
            'target_capaian.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $upt = trim((string) $request->user()->upt);
        $tahun = TahunRba::selected($request);
        foreach ($indicators as $index => $indicator) {
            $nomor = $index + 1;
            SpmTarget::updateOrCreate(
                ['nama_blud' => $upt, 'tahun_anggaran' => $tahun, 'nomor' => $nomor],
                [
                    'indikator' => $indicator,
                    'target_sasaran' => $request->input("target_sasaran.$nomor"),
                    'target_capaian' => $request->input("target_capaian.$nomor"),
                ]
            );
        }

        return redirect()->route('dokumen-rba.show', ['jenis' => $jenis])
            ->with('success', 'Target SPM berhasil disimpan. Preview dokumen telah diperbarui.');
    }

    public function preview(Request $request, string $jenis)
    {
        $template = $this->template($jenis);
        $path = $this->document($request, $template, 'pdf');

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rba-'.$jenis.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    public function download(Request $request, string $jenis, string $format)
    {
        $template = $this->template($jenis);
        abort_unless(in_array($format, ['word', 'pdf'], true), 404);
        $path = $this->document($request, $template, $format);

        return response()->download($path, 'rba-'.$jenis.'.'.($format === 'word' ? 'docx' : 'pdf'), [
            'Content-Type' => $format === 'word'
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    private function document(Request $request, array $template, string $format): string
    {
        abort_unless(is_file($template['word']), 404, 'Template dokumen belum tersedia.');
        $upt = trim((string) $request->user()->upt);
        abort_if($upt === '', 422, 'UPT pengguna belum tersedia. Lengkapi UPT sebelum membuat dokumen RBA.');
        $tahun = TahunRba::selected($request);
        $spm = SpmTarget::where('nama_blud', $upt)->where('tahun_anggaran', $tahun)
            ->orderBy('nomor')->get();
        if ($format === 'pdf' && ! app(RbaDocumentPdfConverter::class)->isAvailable()) {
            abort(503, 'Konversi PDF tidak tersedia di server ini (membutuhkan LibreOffice pada Linux). Silakan unduh dokumen dalam format Word (.docx).');
        }

        try {
            return app(RbaDocumentGenerator::class)->generate($template['word'], $upt, $tahun, $format, $spm->all());
        } catch (\Throwable $exception) {
            report($exception);
            abort(503, 'Dokumen RBA gagal dibuat. Silakan coba kembali atau hubungi administrator.');
        }
    }

    private function template(string $jenis): array
    {
        $templates = config('dokumen-rba.templates');
        abort_unless(isset($templates[$jenis]), 404);

        return $templates[$jenis];
    }
}
