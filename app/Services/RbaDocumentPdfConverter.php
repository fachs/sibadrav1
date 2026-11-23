<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class RbaDocumentPdfConverter
{
    public function isAvailable(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        if ($this->canUsePurePhpDomPdf()) {
            return true;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return $this->findLibreOffice() !== null;
        }

        return is_file(base_path('scripts/export-rba-template.ps1'));
    }

    public function convert(string $word, string $pdf): void
    {
        $outDir = dirname($pdf);

        // 1. On Linux, check for LibreOffice / soffice (highest layout fidelity if installed)
        if (PHP_OS_FAMILY !== 'Windows') {
            $libreOffice = $this->findLibreOffice();
            if ($libreOffice) {
                $process = new Process([
                    $libreOffice, '--headless', '--convert-to', 'pdf:writer_pdf_Export',
                    '--outdir', $outDir, $word,
                ], null, null, null, 120);
                $process->run();

                $generatedPdf = $outDir.'/'.pathinfo($word, PATHINFO_FILENAME).'.pdf';
                if (is_file($generatedPdf)) {
                    if ($generatedPdf !== $pdf) {
                        rename($generatedPdf, $pdf);
                    }

                    return;
                }
            }
        }

        // 2. On Windows, use PowerShell & Word COM if available
        if (PHP_OS_FAMILY === 'Windows' && is_file(base_path('scripts/export-rba-template.ps1'))) {
            $environment = getenv() ?: null;
            $process = new Process([
                config('dokumen-rba.powershell', 'powershell.exe'), '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
                '-File', base_path('scripts/export-rba-template.ps1'), '-InputPath', $word, '-OutputPath', $pdf,
            ], base_path(), $environment, null, 120);
            $process->run();

            if (is_file($pdf) && file_get_contents($pdf, false, null, 0, 5) === '%PDF-') {
                return;
            }
        }

        // 3. Pure PHP fallback using DocxToHtmlConverter + DomPDF (works on cPanel shared hosting without LibreOffice)
        if ($this->canUsePurePhpDomPdf()) {
            $this->convertWithDomPdf($word, $pdf);

            return;
        }

        throw new RuntimeException('Konversi PDF tidak tersedia di server ini. Silakan unduh dokumen dalam format Word (.docx).');
    }

    public function convertWithDomPdf(string $word, string $pdf): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $html = app(DocxToHtmlConverter::class)->convertToHtml($word);

        $dompdf = Pdf::loadHTML($html);
        $dompdf->setOption('isHtml5ParserEnabled', true);
        $dompdf->setOption('isRemoteEnabled', false);

        $output = $dompdf->output();
        if (! is_string($output) || ! str_starts_with($output, '%PDF-')) {
            throw new RuntimeException('DomPDF gagal menghasilkan berkas PDF yang valid.');
        }

        if (file_put_contents($pdf, $output) === false) {
            throw new RuntimeException("Gagal menyimpan berkas PDF ke: {$pdf}");
        }
    }

    private function canUsePurePhpDomPdf(): bool
    {
        return class_exists(Pdf::class) && class_exists(ZipArchive::class);
    }

    private function findLibreOffice(): ?string
    {
        $configured = config('dokumen-rba.libreoffice_path', env('LIBREOFFICE_PATH'));
        if ($configured && (is_executable($configured) || is_file($configured))) {
            return $configured;
        }

        $commonPaths = [
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
            '/usr/local/bin/libreoffice',
            '/usr/local/bin/soffice',
            '/opt/libreoffice/program/soffice',
            '/snap/bin/libreoffice',
        ];

        foreach ($commonPaths as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        foreach (['libreoffice', 'soffice'] as $binary) {
            $process = new Process(['which', $binary]);
            $process->run();
            if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
                $found = trim($process->getOutput());
                if (is_file($found) && is_executable($found)) {
                    return $found;
                }
            }
        }

        return null;
    }
}

