<?php

namespace Tests\Unit;

use App\Services\RbaDocumentGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

class RbaDocumentAppendicesTest extends TestCase
{
    public function test_three_pdf_style_tables_follow_conclusion_with_dynamic_data(): void
    {
        $zip = new ZipArchive;
        self::assertTrue($zip->open(__DIR__.'/../../resources/documents/rba/template-rba.docx'));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        self::assertIsString($xml);

        $generator = new RbaDocumentGenerator;
        $sources = [
            'replacePendapatanTable' => [[
                'kode' => '4.1.02.01.01.0001', 'uraian' => 'Pendapatan Lampiran Uji', 'jumlah' => 125000,
                'details' => [],
            ]],
            'replaceBelanjaTable' => [[
                'kode' => '5.1.02.01.001.00052', 'uraian' => 'Belanja Lampiran Uji', 'jumlah' => 50000,
                'details' => [[
                    'uraian' => 'Item Lampiran Uji', 'spesifikasi' => 'Ukuran Uji',
                    'volume' => 2, 'satuan' => 'buah', 'harga_satuan' => 25000, 'jumlah' => 50000,
                ]],
            ]],
            'replacePembiayaanTable' => [[
                'kode' => '6.1.01.01.01.0001', 'uraian' => 'Pembiayaan Lampiran Uji', 'jumlah' => 30000,
            ]],
        ];
        foreach ($sources as $method => $rows) {
            $xml = (new ReflectionMethod(RbaDocumentGenerator::class, $method))->invoke($generator, $xml, $rows);
        }
        $xml = (new ReflectionMethod(RbaDocumentGenerator::class, 'appendRbaPdfTables'))
            ->invoke($generator, $xml, 'Puskesmas Uji', 2026, [
                '{Tanggal}' => '04 November 2026',
                '{Nama Kepala Puskesmas}' => 'dr. Kepala Uji',
                '{NIP Kepala Puskesmas}' => '19800101 200501 1 016',
            ], ['alamat' => 'Jalan Uji', 'kinerja' => ['masukan_target' => '100%']]);

        foreach (['PENDAPATAN', 'BELANJA', 'PEMBIAYAAN'] as $type) {
            self::assertSame(1, substr_count($xml, 'ANGGARAN '.$type.'</w:t>'));
        }
        self::assertSame(3, substr_count($xml, 'r:embed="rIdRbaLogo"'));
        self::assertSame(3, substr_count($xml, 'dr. Kepala Uji'));
        self::assertSame(3, substr_count($xml, '19800101 200501 1 016'));
        self::assertSame(3, substr_count($xml, 'w:line="1200" w:lineRule="exact"'));
        self::assertSame(2, substr_count($xml, 'Pendapatan Lampiran Uji'));
        self::assertSame(2, substr_count($xml, 'Pembiayaan Lampiran Uji'));
        self::assertSame(2, substr_count($xml, 'Spesifikasi: Ukuran Uji'));
        self::assertStringContainsString('Target Kinerja', $xml);
        self::assertStringContainsString('100%', $xml);
        self::assertStringNotContainsString('{HALAMAN HASIL UNDUH RBA', $xml);
    }
}
