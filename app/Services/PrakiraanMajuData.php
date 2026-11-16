<?php

namespace App\Services;

use App\Models\PrakiraanMaju;
use App\Models\RencanaKebutuhan;

class PrakiraanMajuData
{
    public const JENIS_BELANJA = [
        '5.1.01' => 'Belanja Pegawai BLUD',
        '5.1.02' => 'Belanja Barang dan Jasa BLUD',
        '5.2.02' => 'Belanja Modal Peralatan dan Mesin BLUD',
        '5.2.03' => 'Belanja Modal Gedung dan Bangunan BLUD',
        '5.2.04' => 'Belanja Modal Jalan, Jaringan dan Irigasi BLUD',
    ];

    public function baselineTotals(string $namaBlud, int $tahun): array
    {
        $rows = RencanaKebutuhan::with('akuns')
            ->where('nama_blud', $namaBlud)
            ->where('tahun_anggaran', (string) $tahun)
            ->whereNotNull('akun_id')
            ->where(fn ($query) => $query->where('is_hasil_perubahan', false)->orWhereNull('is_hasil_perubahan'))
            ->get();

        $totals = array_fill_keys(array_keys(self::JENIS_BELANJA), 0);
        foreach ($rows as $row) {
            $kode = optional($row->akuns)->kode_rek ?? '';
            foreach (array_keys($totals) as $prefix) {
                if (str_starts_with($kode, $prefix)) {
                    $totals[$prefix] += (float) $row->jumlah_harga;
                    break;
                }
            }
        }

        return $totals;
    }

    public function rows(string $namaBlud, int $tahun): array
    {
        $baseline = $this->baselineTotals($namaBlud, $tahun);
        $saved = PrakiraanMaju::where('nama_blud', $namaBlud)
            ->where('tahun_baseline', $tahun)
            ->get()->keyBy('kode_rek');

        $rows = [];
        foreach (self::JENIS_BELANJA as $kode => $uraian) {
            $item = $saved->get($kode);
            $rows[] = [
                'kode_rek' => $kode,
                'uraian' => $uraian,
                'nilai_baseline' => $baseline[$kode] ?? 0,
                'nilai_tahun_1' => $item?->nilai_tahun_1 ?? 0,
                'nilai_tahun_2' => $item?->nilai_tahun_2 ?? 0,
            ];
        }

        return $rows;
    }
}
