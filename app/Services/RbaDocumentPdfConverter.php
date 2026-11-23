<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
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
}

