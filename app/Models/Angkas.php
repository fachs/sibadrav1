<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Angkas extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'angkases';

    protected $fillable = [
        'unit_blud_id',
        'tahun_anggaran',
        'jenis_belanja',
        'alokasi_anggaran',
        'jumlah',
        'bulan',
    ];
}
