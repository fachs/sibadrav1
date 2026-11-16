<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class RbaDocumentPdfConverter
{
    public function convert(string $word, string $pdf): void
    {
        // Symfony filters its default environment through $_SERVER on web requests.
        // Explicitly preserve the native Windows environment needed by PowerShell and Word COM.
        $environment = PHP_OS_FAMILY === 'Windows' ? getenv() : null;
        $process = new Process([
            config('dokumen-rba.powershell'), '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
            '-File', base_path('scripts/export-rba-template.ps1'), '-InputPath', $word, '-OutputPath', $pdf,
        ], base_path(), $environment, null, 120);
        $process->mustRun();
    }
}
