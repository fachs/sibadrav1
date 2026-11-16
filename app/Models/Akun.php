<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Akun extends Model
{
    use HasFactory;

    protected $table = 'akuns';

    protected $fillable = [
        'akun',
        'kelompok',
        'jenis',
        'objek',
        'rincian_objek',
        'sub_ro',
        'kode_rek',
        'uraian',
        'keterangan'
    ];

    public function rencanaKebutuhans()
    {
        return $this->hasMany(RencanaKebutuhan::class, 'akun_id');
    }
}
