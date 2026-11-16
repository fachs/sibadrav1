<?php

namespace Database\Seeders;

use App\Models\RencanaPembiayaan;
use App\Models\UnitBlud;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbaPembiayaanDummy2026Seeder extends Seeder
{
    public function run(): void
    {
        $unit = UnitBlud::where('nama', 'Puskesmas Limusnunggal')->firstOrFail();
        $user = User::where('upt', $unit->nama)->withRole('Pejabat Keuangan')->firstOrFail();

        DB::transaction(function () use ($unit, $user) {
            foreach ([
                ['6.1.01.01.01.0001', 'Penggunaan SiLPA tahun sebelumnya', 1, 'Tahun', 60000000],
                ['6.1.02.01.01.0001', 'Hasil penjualan kekayaan daerah yang dipisahkan pada BLUD', 1, 'Paket', 15000000],
                ['6.1.03.02.01.0001', 'Penerimaan pinjaman jangka panjang BLUD', 1, 'Pinjaman', 30000000],
                ['6.1.03.03.01.0001', 'Penerimaan pinjaman dana bergulir dari BUD', 2, 'Tahap', 10000000],
                ['6.2.01.01.01.0001', 'Investasi pengembangan layanan BLUD', 2, 'Paket', 5000000],
                ['6.2.02.01.01.0001', 'Pembayaran cicilan pokok utang BLUD', 12, 'Bulan', 1000000],
                ['6.2.03.01.01.0001', 'Pemberian pinjaman dana bergulir kepada kreditur', 5, 'Paket', 1000000],
            ] as [$code, $description, $volume, $measure, $price]) {
                $detail = [
                    'harga_satuan' => $price,
                    'volume_1' => $volume, 'satuan_1' => $measure,
                    'volume_2' => 1, 'satuan_2' => 'Tahun',
                    'volume_3' => 1, 'satuan_3' => 'Unit',
                    'volume_4' => 1, 'satuan_4' => 'Dummy 2026',
                ];

                RencanaPembiayaan::firstOrCreate([
                    'nama_blud' => $unit->nama,
                    'tahun_anggaran' => 2026,
                    'kode_rek' => $code,
                    'uraian' => $description.' (Dummy 2026)',
                ], $detail + [
                    'user_id' => $user->id,
                    'jumlah' => RencanaPembiayaan::hitungJumlah($detail),
                ]);
            }
        });
    }
}
