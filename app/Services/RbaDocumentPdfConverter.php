<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class RbaDocumentPdfConverter
{
    public function isAvailable(): bool
    {
        if (app()->environment('testing')) {
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

        // 1. On Linux, check for LibreOffice / soffice
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
        if (PHP_OS_FAMILY === 'Windows') {
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

        throw new RuntimeException('Konversi PDF tidak tersedia di server ini (membutuhkan LibreOffice pada Linux). Silakan unduh dokumen dalam format Word (.docx).');
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

