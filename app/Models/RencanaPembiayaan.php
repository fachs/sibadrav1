<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class RencanaPembiayaan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nama_blud',
        'tahun_anggaran',
        'kode_rek',
        'uraian',
        'jumlah',
        'uraian_perubahan',
        'jumlah_perubahan',
        'status_perubahan',
        'verifikator_perubahan',
        'kode_rek_perubahan', 'harga_satuan', 'harga_satuan_perubahan',
        'volume_1', 'volume_2', 'volume_3', 'volume_4', 'satuan_1', 'satuan_2', 'satuan_3', 'satuan_4',
        'volume_1_perubahan', 'volume_2_perubahan', 'volume_3_perubahan', 'volume_4_perubahan',
        'satuan_1_perubahan', 'satuan_2_perubahan', 'satuan_3_perubahan', 'satuan_4_perubahan',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        $casts = ['tahun_anggaran' => 'integer', 'harga_satuan' => 'decimal:2', 'harga_satuan_perubahan' => 'decimal:2', 'jumlah' => 'decimal:2', 'jumlah_perubahan' => 'decimal:2'];
        for ($i = 1; $i <= 4; $i++) {
            $casts['volume_'.$i] = 'decimal:4';
            $casts['volume_'.$i.'_perubahan'] = 'decimal:4';
        }

        return $casts;
    }

    public static function hitungJumlah(array $data): string
    {
        $total = BigDecimal::of((string) $data['harga_satuan']);
        for ($i = 1; $i <= 4; $i++) {
            $total = $total->multipliedBy((string) ($data['volume_'.$i] ?? 1));
        }

        return (string) $total->toScale(2, RoundingMode::HalfUp);
    }

    public function rincian(bool $perubahan = false): array
    {
        $data = [];
        foreach (['kode_rek', 'uraian', 'harga_satuan', 'jumlah', 'volume_1', 'volume_2', 'volume_3', 'volume_4', 'satuan_1', 'satuan_2', 'satuan_3', 'satuan_4'] as $field) {
            $data[$field] = ($perubahan ? $this->getAttribute($field.'_perubahan') : null) ?? $this->getAttribute($field);
        }

        return $data;
    }
}
