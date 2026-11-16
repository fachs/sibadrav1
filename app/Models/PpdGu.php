<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PpdGu extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ppd_gus';

    protected $fillable = [
        'nomor',
        'jumlah',
        'nama_bendahara',
        'no_rek',
        'no_sk',
        'tanggal_sk',
        'unit_blud_id',
        'tahun_anggaran',
        'bank_asal',
        'kepada',
        'npwp',
        'bank_tujuan',
        'no_rek_tujuan',
        'potongan',
        'jumlah_akhir',
    ];

    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class);
    }

    public function opdGu()
    {
        return $this->hasOne(OpdGu::class, 'ppd_gu_id');
    }
}