<?php

namespace Tests\Unit;

use App\Services\RbaDocumentGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RbaDocumentTemplateTextTest extends TestCase
{
    public function test_budget_year_and_conclusion_signer_follow_document_values(): void
    {
        $xml = '<w:p><w:r><w:t>Tahun 20</w:t></w:r><w:r><w:t>27</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>tahun anggaran 2027</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Pemimpin BLUD</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>dr. Kepala Sosialisasi15</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>NIP. 19800101 200501 1 015</w:t></w:r></w:p>';
        $generator = new RbaDocumentGenerator;
        $xml = (new ReflectionMethod(RbaDocumentGenerator::class, 'replaceTemplateBudgetYear'))
            ->invoke($generator, $xml, 2026);
        $xml = (new ReflectionMethod(RbaDocumentGenerator::class, 'replaceConclusionSignature'))
            ->invoke($generator, $xml, [
                '{Nama Kepala Puskesmas}' => 'dr. Pemimpin Uji',
                '{NIP Kepala Puskesmas}' => '19790101 200401 2 012',
            ]);

        $text = strip_tags($xml);
        self::assertStringContainsString('Tahun 2026', $text);
        self::assertStringContainsString('tahun anggaran 2026', $text);
        self::assertStringContainsString('dr. Pemimpin Uji', $text);
        self::assertStringContainsString('NIP. 19790101 200401 2 012', $text);
        self::assertStringNotContainsString('Kepala Sosialisasi15', $text);
    }
}
