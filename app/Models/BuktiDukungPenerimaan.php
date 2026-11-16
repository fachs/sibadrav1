<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuktiDukungPenerimaan extends Model
{
    use HasFactory;

    protected $table = 'bukti_dukung_penerimaans';

    protected $fillable = [
        'periode',
        'link',
        'nama_blud',
        'tahun_anggaran',
        'penerimaan_id',
        'unit_blud_id',
    ];

    public function penerimaan()
    {
        return $this->belongsTo(Penerimaan::class, 'penerimaan_id', 'id');
    }

    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class, 'unit_blud_id', 'id');
    }
}