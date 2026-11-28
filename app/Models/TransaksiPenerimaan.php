<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiPenerimaan extends Model
{
    use HasFactory;

    protected $table = 'transaksi_penerimaans';

    protected $fillable = [
        'penerimaan_id',
        'tanggal',
        'nomor_transaksi',
        'nomor_tbp',
        'nama_penjamin',
        'metode_pembayaran',
        'total_biaya',
        'status',
    ];

    protected $casts = [
        'total_biaya' => 'integer',
    ];

    public function penerimaan()
    {
        return $this->belongsTo(Penerimaan::class, 'penerimaan_id');
    }
}
