<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class RbaDocumentPdfConverter
{
    public function isAvailable(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        return $this->canUsePurePhpDomPdf();
    }

    public function convert(string $word, string $pdf): void
    {
        if (! $this->canUsePurePhpDomPdf()) {
            throw new RuntimeException('Konversi PDF tidak tersedia di server ini (membutuhkan ekstensi PHP ZipArchive dan DomPDF).');
        }

        $this->convertWithDomPdf($word, $pdf);
    }

    public function convertWithDomPdf(string $word, string $pdf): void
    {
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        File::ensureDirectoryExists(storage_path('fonts'));

        $html = app(DocxToHtmlConverter::class)->convertToHtml($word);

        $dompdf = Pdf::loadHTML($html);
        unset($html);
        $dompdf->setOption('isHtml5ParserEnabled', true);
        $dompdf->setOption('isRemoteEnabled', false);
        $dompdf->setOption('fontDir', storage_path('fonts'));
        $dompdf->setOption('fontCache', storage_path('fonts'));

        $output = $dompdf->output();
        unset($dompdf);
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
}

