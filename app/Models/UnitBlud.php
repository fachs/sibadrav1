<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitBlud extends Model
{
    use HasFactory;

    protected $table = 'unit_bluds';

    protected $fillable = [
        'nama',
        'kepala',
        'kasubbag_tu',
        'kontak',
        'alamat',
        'nip_kapus',
        'nip_ktu',
    ];

    // public function kepala()
    // {
    //     return $this->belongsTo(User::class, 'kepala_id');
    // }

    // public function kasubbagTu()
    // {
    //     return $this->belongsTo(User::class, 'kasubbag_tu_id');
    // }

    // public function bendahara()
    // {
    //     return $this->belongsTo(User::class, 'bendahara_id');
    // }

    public function kinerjaKegiatans()
    {
        return $this->hasMany(KinerjaKegiatan::class);
    }

    public function rekeningBluds()
    {
        return $this->hasMany(RekeningBlud::class, 'unit_blud_id');
    }
}
