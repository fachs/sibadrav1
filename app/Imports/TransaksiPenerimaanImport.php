<?php

namespace App\Imports;

use App\Models\TransaksiPenerimaan;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Row;

class TransaksiPenerimaanImport implements ToModel, WithHeadingRow, WithEvents, WithStartRow
{
    use RemembersRowNumber;

    protected $penerimaanId;
    protected int $headerRow = 1;

    public function __construct($penerimaanId)
    {
        $this->penerimaanId = $penerimaanId;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $worksheet = $event->sheet->getDelegate();
                $this->rowNumber = null;
                $this->headerRow = 1;
                $required = [
                    'tanggal', 'nomor_transaksi', 'nama_penjamin', 'total', 'metode_pembayaran',
                ];
                $missing = $required;

                foreach ($worksheet->getRowIterator() as $excelRow) {
                    $headings = array_map(function ($value) {
                        $value = preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $value);

                        return Str::slug(trim($value), '_');
                    }, (new Row($excelRow))->toArray(null, false, false));
                    $rowMissing = array_diff($required, $headings);

                    if (count($rowMissing) < count($missing)) {
                        $missing = $rowMissing;
                    }

                    if (!$rowMissing) {
                        $this->headerRow = $excelRow->getRowIndex();
                        $worksheet->fromArray([$headings], null, 'A' . $this->headerRow);

                        return;
                    }
                }

                throw new \InvalidArgumentException('Header tabel Excel tidak ditemukan. Kolom yang belum terbaca: ' . implode(', ', $missing));
            },
        ];
    }

    public function headingRow(): int
    {
        return $this->headerRow;
    }

    public function startRow(): int
    {
        // The reader determines the start row before BeforeSheet detects the header.
        return 1;
    }

    public function model(array $row)
    {
        if ($this->getRowNumber() !== null && $this->getRowNumber() <= $this->headerRow) {
            return null;
        }

        $namaPenjamin = trim((string) ($row['nama_penjamin'] ?? ''));

        if (strcasecmp($namaPenjamin, 'Umum') !== 0) {
            return null;
        }

        $tanggal = $row['tanggal'] ?? null;
        $parsedTanggal = null;

        if (is_numeric($tanggal)) {
            try {
                $parsedTanggal = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tanggal)->format('Y-m-d');
            } catch (\Exception $e) {}
        } else {
            // Coba parse string
            try {
                if ($tanggal) {
                    // Cek minimal string terlihat seperti tanggal (mengandung angka/pemisah)
                    if (preg_match('/[0-9]/', $tanggal)) {
                        $parsedTanggal = \Carbon\Carbon::parse($tanggal)->format('Y-m-d');
                    }
                }
            } catch (\Exception $e) {
                // Gagal parse
            }
        }

        // Jika tanggal tetap tidak valid (contoh: baris metadata, header teks, dsb), lewati baris ini
        if (!$parsedTanggal) {
            return null;
        }

        $totalBiayaRaw = preg_replace('/[^0-9]/', '', $row['total'] ?? '0');
        $totalBiaya = empty($totalBiayaRaw) ? 0 : (int) $totalBiayaRaw;

        return new TransaksiPenerimaan([
            'penerimaan_id' => $this->penerimaanId,
            'tanggal' => $parsedTanggal,
            'nomor_transaksi' => $row['nomor_transaksi'] ?? '-',
            'nama_penjamin' => $namaPenjamin,
            'total_biaya' => $totalBiaya,
            'metode_pembayaran' => $row['metode_pembayaran'] ?? '-',
            'status' => 'Selesai',
        ]);
    }
}
