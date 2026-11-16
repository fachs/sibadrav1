<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KinerjaKegiatan extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'unit_blud_id',
        'tahun',
        'capaian_program_tolak_ukur',
        'capaian_program_target',
        'masukan_tolak_ukur',
        'masukan_target',
        'keluaran_tolak_ukur',
        'keluaran_target',
        'capaian_program_tolak_ukur_perubahan',
        'capaian_program_target_perubahan',
        'masukan_tolak_ukur_perubahan',
        'masukan_target_perubahan',
        'keluaran_tolak_ukur_perubahan',
        'keluaran_target_perubahan',
        'status_perubahan',
        'verifikator_perubahan',
    ];

    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class);
    }
}
