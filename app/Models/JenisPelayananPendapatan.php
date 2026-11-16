<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;

class JenisPelayananPendapatan extends Model
{
    public const UNIT_PELAYANAN = ['Klaster 1', 'Klaster 2', 'Klaster 3', 'Klaster 4', 'Lintas Klaster'];

    protected $fillable = [
        'unit_pelayanan', 'uraian', 'volume_1', 'volume_2', 'volume_3', 'volume_4',
        'satuan_1', 'satuan_2', 'satuan_3', 'satuan_4', 'harga_satuan', 'jumlah',
        'gunakan_cagr', 'realisasi_tahun_3', 'realisasi_tahun_2', 'realisasi_jan_jun',
    ];

    protected $casts = [
        'volume_1' => 'decimal:4', 'volume_2' => 'decimal:4',
        'volume_3' => 'decimal:4', 'volume_4' => 'decimal:4',
        'harga_satuan' => 'decimal:2', 'jumlah' => 'decimal:2', 'is_data_lama' => 'boolean',
        'gunakan_cagr' => 'boolean', 'realisasi_tahun_3' => 'decimal:2',
        'realisasi_tahun_2' => 'decimal:2', 'realisasi_jan_jun' => 'decimal:2',
    ];

    public function rencanaPendapatan()
    {
        return $this->belongsTo(RencanaPendapatan::class);
    }

    public static function hitungJumlah(array $data): string
    {
        $jumlah = BigDecimal::of((string) $data['harga_satuan']);
        for ($i = 1; $i <= 4; $i++) {
            $jumlah = $jumlah->multipliedBy((string) ($data['volume_'.$i] ?? 1));
        }

        return (string) $jumlah->toScale(2, RoundingMode::HalfUp);
    }

    public static function hitungProyeksiCagr(array $data): string
    {
        $awal = (float) ($data['realisasi_tahun_3'] ?? 0);
        $prognosis = (float) ($data['realisasi_jan_jun'] ?? 0) * 2;
        if ($awal <= 0 || $prognosis <= 0) {
            throw new \InvalidArgumentException('Realisasi tahun -3 dan Jan–Jun harus lebih besar dari nol.');
        }
        $proyeksi = round($prognosis * sqrt($prognosis / $awal));
        if (!is_finite($proyeksi) || $proyeksi < 1 || $proyeksi > 9999999999999.99) {
            throw new \InvalidArgumentException('Hasil proyeksi harus minimal 1 dan maksimal 9.999.999.999.999,99. Periksa nilai realisasi.');
        }

        return number_format($proyeksi, 2, '.', '');
    }
}
