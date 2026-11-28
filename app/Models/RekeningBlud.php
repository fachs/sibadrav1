<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RekeningBlud extends Model
{
    use HasFactory;

    public const JENIS_PENERIMAAN = 'Rek. Bendahara Penerimaan';
    public const JENIS_PENGELUARAN = 'Rek. Bendahara Pengeluaran';
    public const JENIS_BLUD = 'Rekening BLUD';

    public const JENIS_LIST = [
        self::JENIS_PENERIMAAN,
        self::JENIS_PENGELUARAN,
        self::JENIS_BLUD,
    ];

    protected $table = 'rekening_bluds';

    protected $fillable = [
        'unit_blud_id',
        'nama_blud',
        'jenis',
        'nama_bank',
        'nomor_rekening',
        'saldo_awal',
        'saldo',
        'updated_by',
    ];

    protected $casts = [
        'saldo_awal' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class, 'unit_blud_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
