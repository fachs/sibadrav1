<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pengembalian extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pengembalians';

    protected $fillable = [
        'tanggal',
        'nomor',
        'jenis_bukti',
        'dasar_pembayaran',
        'no_reg_spm',
        'pihak_ketiga',
        'penolakan',
        'akun_id',
        'penerimaan_id',
        'unit_blud_id',
    ];

    // relasi ke Akun

    // pengembalian -> akun (many to one)
    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    // relasi ke UnitBlud

    // pengembalian -> unit_blud (many to one)
    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class, 'unit_blud_id');
    }

    public function penerimaan()
    {
        return $this->belongsTo(Penerimaan::class, 'penerimaan_id', 'id');
    }

    public function getJumlahAttribute()
    {
        return $this->penerimaan ? $this->penerimaan->jumlah : 0;
    }
}
