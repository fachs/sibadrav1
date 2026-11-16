<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpdGu extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'spd_gus';

    protected $fillable = [
        'nomor',
        'unit_blud_id',
        'nomor_dba',
        'tanggal_dba',
        'opd_gu_id',
        'tahun_anggaran',
        'bank_asal',
        'jumlah',
        'kepada',
        'npwp',
        'no_rek_tujuan',
        'bank_tujuan',
        'potongan',
        'jumlah_akhir',
    ];

    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class, 'unit_blud_id', 'id');
    }

    public function opdGu()
    {
        return $this->belongsTo(OpdGu::class, 'opd_gu_id', 'id');
    }
}