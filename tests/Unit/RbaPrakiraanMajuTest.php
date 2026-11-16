<?php

namespace Tests\Unit;

use App\Services\RbaDocumentGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

class RbaPrakiraanMajuTest extends TestCase
{
    public function test_section_g_uses_selected_year_and_five_rows_from_prakiraan_page(): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(dirname(__DIR__, 2).'/resources/documents/rba/template-rba.docx'));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $generator = new RbaDocumentGenerator;
        $narrative = new ReflectionMethod($generator, 'replacePrakiraanNarrativeYears');
        $xml = $narrative->invoke($generator, $xml, 2026);
        $tableMethod = new ReflectionMethod($generator, 'replaceTablesByIndex');
        $rows = [
            [1, '5.1.01', 'Belanja Pegawai BLUD', '100.000', '110.000', '120.000'],
            [2, '5.1.02', 'Belanja Barang dan Jasa BLUD', '200.000', '210.000', '220.000'],
            [3, '5.2.02', 'Belanja Modal Peralatan dan Mesin BLUD', '0', '0', '0'],
            [4, '5.2.03', 'Belanja Modal Gedung dan Bangunan BLUD', '0', '0', '0'],
            [5, '5.2.04', 'Belanja Modal Jalan, Jaringan dan Irigasi BLUD', '0', '0', '0'],
        ];
        $xml = $tableMethod->invoke($generator, $xml, [8 => [
            $rows,
            ['TOTAL PRAKIRAAN MAJU', '300.000', '320.000', '340.000'],
            ['No', 'Kode', 'Nama Jenis Belanja', 'Tahun 2026 (Rp)', 'Tahun 2027 (Rp)', 'Tahun 2028 (Rp)'],
        ]]);

        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $tables);
        $table = $tables[0][8];
        preg_match_all('~<w:tr(?:\s[^>]*)?>.*?</w:tr>~s', $table, $tableRows);
        $this->assertCount(7, $tableRows[0]);
        $text = html_entity_decode(strip_tags($table), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $documentText = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $this->assertStringContainsString('Tahun 2026 (Rp)', $text);
        $this->assertStringContainsString('Tahun 2027 (Rp)', $text);
        $this->assertStringContainsString('Tahun 2028 (Rp)', $text);
        $this->assertStringNotContainsString('Tahun 2029 (Rp)', $text);
        $this->assertStringContainsString('5.1.01Belanja Pegawai BLUD100.000110.000120.000', $text);
        $this->assertStringContainsString('5.2.04Belanja Modal Jalan, Jaringan dan Irigasi BLUD000', $text);
        $this->assertStringContainsString('TOTAL PRAKIRAAN MAJU300.000320.000340.000', $text);
        $this->assertStringContainsString('untuk tahun 2027 dan 2028', $documentText);
        $this->assertStringContainsString('Proyeksi tahun 2027 dan 2028 mengikuti nilai Prakiraan Maju yang ditetapkan', $documentText);
        $this->assertStringContainsString('Nilai tahun dasar diambil dari total RBA Belanja pada tahun anggaran terpilih', $documentText);
        $this->assertStringNotContainsString('laju pertumbuhan 1-3%', $documentText);
    }
}
