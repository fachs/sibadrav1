<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Penerimaan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'penerimaans';

    protected $fillable = [
        'uuid',
        'tahun_anggaran',
        'periode',
        'tanggal_terima',
        'tanggal_setor',
        'nomor_terima',
        'nomor_tbp',
        'cara_pembayaran',
        'kode_rekening',
        'akun_id',
        'uraian',
        'unit_blud_id',
    ];

    protected static function booted()
    {
        static::creating(function ($penerimaan) {
            if (empty($penerimaan->uuid)) {
                $penerimaan->uuid = (string) Str::uuid();
            }
        });
    }

    // relasi ke TransaksiPenerimaan
    public function transaksis()
    {
        return $this->hasMany(TransaksiPenerimaan::class, 'penerimaan_id');
    }

    // relasi ke Akun

    // penerimaan -> akun (many to one)
    public function akuns()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    // relasi ke UnitBlud

    // penerimaan -> unit_blud (many to one)
    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class, 'unit_blud_id');
    }

    // accessor for jumlah from transaksi_penerimaans
    public function getJumlahAttribute()
    {
        return $this->transaksis->sum('total_biaya');
    }

    public function isTbpPerTransaksi(): bool
    {
        return in_array($this->cara_pembayaran, ['Kas Penerimaan BLUD', 'Kas BLUD'], true);
    }
}
