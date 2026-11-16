<?php

namespace Database\Seeders;

use App\Models\Akun;
use App\Models\JenisPelayananPendapatan;
use App\Models\RencanaKebutuhan;
use App\Models\RencanaPendapatan;
use App\Models\UnitBlud;
use App\Models\User;
use App\Support\ReferensiPendapatan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbaDummy2026Seeder extends Seeder
{
    public function run(): void
    {
        $unit = UnitBlud::where('nama', 'Puskesmas Limusnunggal')->firstOrFail();
        $user = User::where('upt', $unit->nama)->withRole('Pejabat Keuangan')->firstOrFail();
        $references = app(ReferensiPendapatan::class);

        DB::transaction(function () use ($unit, $user, $references) {
            foreach ([
                ['4.1.02.01.01.0001', 'Pelayanan Rawat Jalan', 'Klaster 3', 100, 12, 15000],
                ['4.1.02.01.06.0001', 'Pemeriksaan/Perawatan Kesehatan di Rumah', 'Lintas Klaster', 20, 12, 25000],
                ['4.1.02.01.04.0001', 'Perawatan Luka Tanpa Jahitan', 'Klaster 3', 50, 12, 10000],
                ['4.1.02.02.01.0001', 'Hemoglobin (Fotometri)', 'Lintas Klaster', 100, 12, 8000],
                ['4.1.04.16.02.0001', 'Pendapatan BLUD dari Jasa Layanan', 'Lintas Klaster', 1, 1, 240000000],
                ['4.2.01.01.01.0001', 'Pendapatan Hibah', 'Klaster 1', 1, 1, 50000000],
            ] as [$code, $description, $cluster, $volume, $months, $price]) {
                $reference = $references->rekening($code);
                if (!$reference || !$references->pelayanan($code, $description)) {
                    throw new \RuntimeException('Referensi pelayanan dummy tidak ditemukan: '.$code.' / '.$description);
                }
                $detail = [
                    'unit_pelayanan' => $cluster, 'uraian' => $description,
                    'volume_1' => $volume, 'satuan_1' => $months === 1 ? 'Paket' : 'Pelayanan',
                    'volume_2' => $months, 'satuan_2' => $months === 1 ? 'Tahun' : 'Bulan',
                    'volume_3' => 1, 'satuan_3' => 'Unit', 'volume_4' => 1, 'satuan_4' => 'Dummy 2026',
                    'harga_satuan' => $price, 'gunakan_cagr' => false,
                ];
                $detail['jumlah'] = JenisPelayananPendapatan::hitungJumlah($detail);
                $plan = RencanaPendapatan::firstOrCreate([
                    'nama_blud' => $unit->nama, 'tahun_anggaran' => 2026, 'kode_rek' => $code, 'is_hasil_perubahan' => false,
                ], ['uraian' => $reference['uraian'].' (Dummy 2026)', 'jumlah' => $detail['jumlah'], 'user_id' => $user->id]);
                if ($plan->wasRecentlyCreated) {
                    $plan->jenisPelayanans()->create($detail);
                }
            }

            foreach ([
                ['5.1.01.99.099.09999', 'Honorarium tenaga pelayanan BLUD', 12, 15000000, 'Bulan'],
                ['5.1.02.01.001.00037', 'Pengadaan obat pelayanan umum', 1000, 10000, 'Strip'],
                ['5.1.02.99.099.09999', 'Bahan habis pakai pelayanan', 100, 100000, 'Paket'],
                ['5.1.02.01.001.00024', 'Alat tulis kantor puskesmas', 12, 500000, 'Paket'],
                ['5.1.02.02.001.00061', 'Tagihan listrik puskesmas', 12, 1000000, 'Bulan'],
                ['5.1.02.02.001.00060', 'Tagihan air puskesmas', 12, 250000, 'Bulan'],
                ['5.1.02.03.003.00001', 'Pemeliharaan gedung pelayanan', 12, 500000, 'Paket'],
                ['5.2.02.10.001.00002', 'Komputer pelayanan dan administrasi', 2, 7500000, 'Unit'],
            ] as [$code, $description, $quantity, $price, $measure]) {
                $account = Akun::where('kode_rek', $code)->firstOrFail();
                RencanaKebutuhan::firstOrCreate([
                    'nama_blud' => $unit->nama, 'tahun_anggaran' => '2026', 'akun_id' => $account->id,
                    'uraian' => $description.' (Dummy 2026)',
                ], [
                    'nama_unit' => 'Lintas Klaster',
                    'program' => '1.02.01 - PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA',
                    'kegiatan' => '1.02.01.2.10 - Peningkatan Pelayanan BLUD',
                    'subkegiatan' => '1.02.01.2.10.0001 - Pelayanan dan Penunjang Pelayanan BLUD',
                    'jumlah' => $quantity, 'harga_satuan' => $price, 'jumlah_harga' => $quantity * $price,
                    'satuan' => $measure, 'ruang' => 'Puskesmas', 'sumber_dana' => 'BLUD',
                    'keterangan' => 'DUMMY-RBA-2026: data simulasi Puskesmas Limusnunggal',
                    'spesifikasi' => 'Data dummy untuk pengujian tahun anggaran 2026', 'status' => 'draft',
                    'is_hasil_perubahan' => false,
                ]);
            }
        });
    }
}
