<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpmTarget extends Model
{
    protected $table = 'spm_targets';

    protected $fillable = [
        'nama_blud', 'tahun_anggaran', 'nomor', 'indikator', 'target_sasaran', 'target_capaian',
    ];

    protected $casts = [
        'tahun_anggaran' => 'integer', 'nomor' => 'integer',
        'target_sasaran' => 'decimal:2', 'target_capaian' => 'decimal:2',
    ];
}
