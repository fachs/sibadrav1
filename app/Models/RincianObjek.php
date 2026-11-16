<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RincianObjek extends Model
{
    use HasFactory;

    protected $table = 'rincian_objeks';

    protected $fillable = [
        'rencana_kebutuhan_id',
        'akun_id',
        'jumlah_ro',
    ];

    // rincian_objek -> akun (many to one)
    public function akuns()
    {
        return $this->belongsTo(Akun::class);
    }

    // rincian_objek -> rencana_kebutuhan (one to many)
    public function rencanaKebutuhans()
    {
        return $this->hasMany(RencanaKebutuhan::class);
    }
        
}
