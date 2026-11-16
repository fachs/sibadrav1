<?php

namespace App\Support;

class ReferensiPembiayaan
{
    public const REKENING = [
        '6.1 - PENERIMAAN PEMBIAYAAN' => [
            '6.1.01.01.01.0001' => 'Penggunaan Sisa Lebih Perhitungan Anggaran (SiLPA) Tahun Anggaran Sebelumnya',
            '6.1.02.01.01.0001' => 'Hasil Penjualan Kekayaan Daerah yang Dipisahkan pada BLUD',
            '6.1.03.02.01.0001' => 'Penerimaan Utang/Pinjaman Jangka Panjang BLUD',
            '6.1.03.03.01.0001' => 'Penerimaan Pinjaman-Dana Bergulir dari BUD',
        ],
        '6.2 - PENGELUARAN PEMBIAYAAN' => [
            '6.2.01.01.01.0001' => 'Investasi',
            '6.2.02.01.01.0001' => 'Pembayaran Cicilan Pokok Utang BLUD yang Jatuh Tempo',
            '6.2.03.01.01.0001' => 'Pemberian Pinjaman-Dana Bergulir kepada Kreditur',
        ],
    ];

    public static function codes(): array
    {
        return array_keys(array_merge(...array_values(self::REKENING)));
    }
}
