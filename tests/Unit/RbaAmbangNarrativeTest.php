<?php

namespace Tests\Unit;

use App\Services\RbaDocumentGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

class RbaAmbangNarrativeTest extends TestCase
{
    public function test_section_f_uses_income_total_and_recalculates_its_threshold(): void
    {
        $template = dirname(__DIR__, 2).'/resources/documents/rba/template-rba.docx';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($template));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $method = new ReflectionMethod(RbaDocumentGenerator::class, 'replaceAmbangNarrative');
        $updated = $method->invoke(new RbaDocumentGenerator, $xml, 601500000, [
            [2024, 0, 0, 0, 1.5],
            [2025, 0, 0, 0, 6.33],
        ]);
        $text = html_entity_decode(strip_tags($updated), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $this->assertStringContainsString('(7,83 : 2) = 3,92%', $text);
        $this->assertStringContainsString('misal diperkirakan Rp.601.500.000,-', $text);
        $this->assertStringContainsString('besaran ambang batas 3,92% X Rp.601.500.000,- = Rp.23.548.725,-', $text);
        $this->assertStringContainsString('(Rp.601.500.000,- + Rp.23.548.725,-) = Rp.625.048.725,-', $text);
        $this->assertStringContainsString('pendapatan melebihi Rp.625.048.725,-', $text);
        $this->assertStringContainsString('diajukan sebesar Rp.601.500.000,-', $text);
    }

    public function test_section_f_uses_the_actual_number_of_available_ambang_rows(): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(dirname(__DIR__, 2).'/resources/documents/rba/template-rba.docx'));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $method = new ReflectionMethod(RbaDocumentGenerator::class, 'replaceAmbangNarrative');
        $updated = $method->invoke(new RbaDocumentGenerator, $xml, 750000000, [
            [2025, 0, 0, 0, 4.25],
        ]);
        $text = html_entity_decode(strip_tags($updated), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $this->assertStringContainsString('(4,25 : 1) = 4,25%', $text);
        $this->assertStringContainsString('misal diperkirakan Rp.750.000.000,-', $text);
        $this->assertStringContainsString('besaran ambang batas 4,25% X Rp.750.000.000,- = Rp.31.875.000,-', $text);
        $this->assertStringContainsString('(Rp.750.000.000,- + Rp.31.875.000,-) = Rp.781.875.000,-', $text);
    }

    public function test_section_b_kesimpulan_uses_income_total_and_ambang_average(): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(dirname(__DIR__, 2).'/resources/documents/rba/template-rba.docx'));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $method = new ReflectionMethod(RbaDocumentGenerator::class, 'replaceKesimpulanNarrative');
        $updated = $method->invoke(new RbaDocumentGenerator, $xml, 2026, 750000000, [
            [2024, 0, 0, 0, 4.25],
        ]);
        $text = html_entity_decode(strip_tags($updated), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $this->assertStringContainsString('Tahun 2026 diajukan sebesar Rp.750.000.000,-', $text);
        $this->assertStringContainsString('ambang batas RBA yang kami tetapkan sebesar 4,25%', $text);
        $this->assertStringNotContainsString('Tahun 2027 diajukan sebesar Rp.601.500.000,-', $text);
    }
}
