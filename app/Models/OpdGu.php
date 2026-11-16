<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpdGu extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'opd_gus';

    protected $fillable = [
        'nomor',
        'unit_blud_id',
        'nomor_dba',
        'tanggal_dba',
        'ppd_gu_id',
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

    public function ppdGu()
    {
        return $this->belongsTo(PpdGu::class, 'ppd_gu_id', 'id');
    }

    public function spdGu()
    {
        return $this->hasOne(SpdGu::class, 'opd_gu_id');
    }
}