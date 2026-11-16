<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PpdUp extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ppd_ups';

    protected $fillable = [
        'nomor',
        'jumlah',
        'nama_bendahara',
        'no_rek',
        'no_sk',
        'tanggal_sk',
        'unit_blud_id',
    ];

    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class);
    }
}