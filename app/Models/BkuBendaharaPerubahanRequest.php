<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class BkuBendaharaPerubahanRequest extends Model
{
    public const KEPALA_PUSKESMAS = 'kepala_puskesmas';
    public const DINAS_KESEHATAN = 'dinas_kesehatan';

    protected $guarded = [];

    public static function verifikatorUntukTanggal($tanggal): string
    {
        return Carbon::parse($tanggal)->format('Y-m') === now('Asia/Jakarta')->format('Y-m')
            ? self::KEPALA_PUSKESMAS
            : self::DINAS_KESEHATAN;
    }

    public function verifikatorSaatIni(): string
    {
        return $this->aksi === 'rincian' || !$this->bku
            ? self::DINAS_KESEHATAN
            : self::verifikatorUntukTanggal($this->bku->tanggal);
    }

    public function bku()
    {
        return $this->belongsTo(BkuBendaharaPengeluaran::class, 'bku_id');
    }

    public function spdLs()
    {
        return $this->belongsTo(SpdLs::class, 'spd_ls_id');
    }

    public function unitBlud()
    {
        return $this->belongsTo(UnitBlud::class);
    }
}
