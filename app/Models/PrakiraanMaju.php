<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrakiraanMaju extends Model
{
    protected $table = 'prakiraan_majus';

    protected $fillable = [
        'nama_blud',
        'tahun_baseline',
        'kode_rek',
        'uraian',
        'nilai_baseline',
        'nilai_tahun_1',
        'nilai_tahun_2',
    ];

    protected $casts = [
        'tahun_baseline' => 'integer',
        'nilai_baseline' => 'decimal:2',
        'nilai_tahun_1' => 'decimal:2',
        'nilai_tahun_2' => 'decimal:2',
    ];
}
