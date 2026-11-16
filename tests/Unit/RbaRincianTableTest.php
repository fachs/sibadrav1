<?php

namespace Tests\Unit;

use App\Services\RbaDocumentGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

class RbaRincianTableTest extends TestCase
{
    public function test_rincian_uses_ringkasan_totals_and_adds_full_account_codes(): void
    {
        $zip = new ZipArchive;
        self::assertTrue($zip->open(__DIR__.'/../../resources/documents/rba/template-rba.docx'));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        self::assertIsString($xml);

        $pendapatan = [
            ['kode' => '4.1.02.01.01.0001', 'uraian' => 'Pendapatan Rawat Jalan', 'jumlah' => 1000000],
            ['kode' => '4.1.02.01.02.0001', 'uraian' => 'Pendapatan Rawat Inap', 'jumlah' => 2000000],
        ];
        $belanja = [['kode' => '5.1.02.01.001.00052', 'uraian' => 'Belanja Kebutuhan Uji', 'jumlah' => 750000]];
        $pembiayaan = [['kode' => '6.1.01.01.01.0001', 'uraian' => 'Penerimaan Pembiayaan Uji', 'jumlah' => 250000]];
        $generator = new RbaDocumentGenerator;
        foreach (['replaceRingkasanTable', 'replaceRincianTable'] as $name) {
            $xml = (new ReflectionMethod(RbaDocumentGenerator::class, $name))
                ->invoke($generator, $xml, $pendapatan, $belanja, $pembiayaan);
        }

        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $tables);
        $ringkasan = $this->tableRows($tables[0][5]);
        $rincian = $this->tableRows($tables[0][6]);

        self::assertContains(['4.1.02', 'Jasa Layanan BLUD', '3.000.000,00'], $rincian);
        self::assertContains(['4.1.02.01.01.0001', 'Pendapatan Rawat Jalan', '1.000.000,00'], $rincian);
        self::assertContains(['4.1.02.01.02.0001', 'Pendapatan Rawat Inap', '2.000.000,00'], $rincian);
        self::assertContains(['5.1.02.01.001.00052', 'Belanja Kebutuhan Uji', '750.000,00'], $rincian);
        self::assertContains(['6.1.01.01.01.0001', 'Penerimaan Pembiayaan Uji', '250.000,00'], $rincian);
        self::assertNotContains(['4.1.02.01.01.0001', 'Pendapatan Rawat Jalan', '1.000.000,00'], $ringkasan);
        self::assertSame($this->amountFor($ringkasan, 'SURPLUS/ DEFISIT'), $this->amountFor($rincian, 'SURPLUS/ DEFISIT'));
        self::assertSame($this->amountFor($ringkasan, 'Pembiayaan Neto'), $this->amountFor($rincian, 'Pembiayaan Neto'));
        self::assertSame($this->amountFor($ringkasan, 'Sisa Lebih Pembiayaan Anggaran Tahun Berkenaan (SiLPA)'),
            $this->amountFor($rincian, 'Sisa Lebih Pembiayaan Anggaran Tahun Berkenaan (SiLPA)'));
    }

    private function tableRows(string $table): array
    {
        preg_match_all('~<w:tr(?:\s[^>]*)?>.*?</w:tr>~s', $table, $rows);

        return array_map(function (string $row): array {
            preg_match_all('~<w:tc(?:\s[^>]*)?>.*?</w:tc>~s', $row, $cells);

            return array_map(function (string $cell): string {
                preg_match_all('~<w:t(?:\s[^>]*)?>(.*?)</w:t>~s', $cell, $texts);

                return html_entity_decode(implode('', $texts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }, $cells[0]);
        }, $rows[0]);
    }

    private function amountFor(array $rows, string $label): string
    {
        foreach ($rows as $row) {
            if (in_array($label, $row, true)) {
                return end($row);
            }
        }

        self::fail('Baris '.$label.' tidak ditemukan.');
    }
}
