<?php

namespace Tests\Feature;

use App\Imports\TransaksiPenerimaanImport;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Imports\HeadingRowExtractor;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class TransaksiPenerimaanImportTest extends TestCase
{
    public function test_import_maps_headers_with_extra_columns_in_different_positions(): void
    {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->fromArray([
            ['Tanggal', 'Petugas Kasir', 'Nomor Transaksi', 'Nama Pasien', 'Ruangan', 'Nama Penjamin', 'Penjamin', 'Total', 'Diskon', 'PPn', 'Pembulatan', 'Bayar', 'Metode Pembayaran'],
            ['2026-10-14', 'Kasir A', 'TRX-001', 'Pasien A', 'Poli Umum', 'Umum', 'BPJS', 'Rp. 15,000', 1000, 500, 100, 14600, 'Transfer'],
        ], null, 'A1', true);
        $import = new TransaksiPenerimaanImport(123);
        $import->registerEvents()[BeforeSheet::class](new BeforeSheet(new Sheet($worksheet), $import));
        $headings = HeadingRowExtractor::extract($worksheet, $import);
        $excelRow = iterator_to_array($worksheet->getRowIterator(2, 2))[2];
        $grouping = HeadingRowExtractor::extractGrouping($headings, $import);
        $model = $import->model((new Row($excelRow, $headings, $grouping))->toArray());

        $this->assertSame(123, $model->penerimaan_id);
        $this->assertSame('2026-10-14', $model->tanggal);
        $this->assertSame('Kasir A', $model->nama_petugas);
        $this->assertSame('TRX-001', $model->nomor_transaksi);
        $this->assertSame('Pasien A', $model->nama_pasien);
        $this->assertSame('Poli Umum', $model->ruangan);
        $this->assertSame('Umum', $model->nama_penjamin);
        $this->assertSame(15000, $model->total_biaya);
        $this->assertSame('Transfer', $model->metode_pembayaran);
        $spreadsheet->disconnectWorksheets();
    }

    public function test_import_only_accepts_umum_from_nama_penjamin(): void
    {
        $import = new TransaksiPenerimaanImport(123);

        foreach (['BPJS', 'Asuransi', 'Umum Lain', '', null] as $penjamin) {
            $this->assertNull($import->model([
                'tanggal' => '2026-10-14',
                'nama_penjamin' => $penjamin,
                'penjamin' => 'Umum',
            ]));
        }

        $model = $import->model([
            'tanggal' => 46309,
            'nama_penjamin' => ' umum ',
            'total' => 15000,
            'metode_pembayaran' => 'QRIS',
        ]);
        $this->assertSame('Umum', $model->nama_penjamin);
        $this->assertSame(15000, $model->total_biaya);
        $this->assertSame('QRIS', $model->metode_pembayaran);
    }

    public function test_import_rejects_missing_payment_method_header(): void
    {
        $worksheet = (new Spreadsheet())->getActiveSheet();
        $worksheet->fromArray([
            ['Tanggal', 'Petugas Kasir', 'Nomor Transaksi', 'Nama Pasien', 'Ruangan', 'Nama Penjamin', 'Total', 'Bayar'],
        ]);
        $import = new TransaksiPenerimaanImport(123);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('metode_pembayaran');
        $import->registerEvents()[BeforeSheet::class](new BeforeSheet(new Sheet($worksheet), $import));
    }

    public function test_excel_reader_finds_header_after_report_metadata_and_normalizes_whitespace(): void
    {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->fromArray([
            ['Laporan Transaksi Kasir'],
            ['Periode Oktober 2026'],
            ['2026-10-14', 'Metadata', 'HEADER', 'Bukan Pasien', 'Poli', 'Umum'],
            [null],
            [' TANGGAL ', "Petugas\nKasir", 'Nomor Transaksi', 'Nama Pasien', 'Ruangan', "Nama\u{00A0}Penjamin", 'Penjamin', 'Total', 'Diskon', 'PPn', 'Pembulatan', 'Bayar', "Metode\r\nPembayaran"],
            ['2026-10-14', 'Kasir A', 'TRX-001', 'Pasien A', 'Poli Umum', 'Umum', 'BPJS', 'Rp. 15,000', 1000, 500, 100, 14600, 'QRIS'],
            ['2026-10-14', 'Kasir B', 'TRX-002', 'Pasien B', 'Poli Umum', 'BPJS', 'Umum', 'Rp. 20,000', 0, 0, 0, 20000, 'Cash'],
        ], null, 'A1', true);
        // Capture models at the persistence boundary without connecting to the database.
        $import = new class(123) extends TransaksiPenerimaanImport {
            public array $models = [];

            public function model(array $row)
            {
                $model = parent::model($row);
                if ($model !== null) {
                    $this->models[] = $model;
                }

                return null;
            }
        };
        $sheet = new Sheet($worksheet);
        $sheet->import($import, $sheet->getStartRow($import));

        $this->assertSame(5, $import->headingRow());
        $this->assertCount(1, $import->models);
        $this->assertSame('TRX-001', $import->models[0]->nomor_transaksi);
        $this->assertSame(15000, $import->models[0]->total_biaya);
        $this->assertSame('QRIS', $import->models[0]->metode_pembayaran);

        // The same importer must reset header detection for subsequent worksheets.
        $nextWorksheet = $spreadsheet->createSheet();
        $nextWorksheet->fromArray([
            ['Tanggal', 'Petugas Kasir', 'Nomor Transaksi', 'Nama Pasien', 'Ruangan', 'Nama Penjamin', 'Total', 'Metode Pembayaran'],
            ['2026-10-14', 'Kasir C', 'TRX-003', 'Pasien C', 'Poli Umum', 'Umum', 25000, 'Transfer'],
        ]);
        $nextSheet = new Sheet($nextWorksheet);
        $nextSheet->import($import, $nextSheet->getStartRow($import));

        $this->assertSame(1, $import->headingRow());
        $this->assertCount(2, $import->models);
        $this->assertSame('TRX-003', $import->models[1]->nomor_transaksi);
        $this->assertSame('Transfer', $import->models[1]->metode_pembayaran);
        $spreadsheet->disconnectWorksheets();
    }
}
