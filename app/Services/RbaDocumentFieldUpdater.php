<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class RbaDocumentFieldUpdater
{
    public function update(string $word): void
    {
        $environment = PHP_OS_FAMILY === 'Windows' ? getenv() : null;
        $process = new Process([
            config('dokumen-rba.powershell'), '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
            '-File', base_path('scripts/update-rba-fields.ps1'), '-InputPath', $word,
        ], base_path(), $environment, null, 120);
        $process->mustRun();
    }
}
