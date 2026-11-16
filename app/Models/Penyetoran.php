<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penyetoran extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'penyetorans';

    protected $fillable = [
        'tanggal',
        'nomor_bukti',
        'jumlah',
        'keterangan',
        'akun_id',
        'penerimaan_id',
        'unit_blud_id',
    ];

    // relasi ke Akun

    // penyetoran -> akun (many to one)
    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    // relasi ke UnitBlud

    // penyetoran -> unit_blud (many to one)
    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class, 'unit_blud_id');
    }

    public function penerimaan()
    {
        return $this->belongsTo(Penerimaan::class, 'penerimaan_id', 'id');
    }
}
