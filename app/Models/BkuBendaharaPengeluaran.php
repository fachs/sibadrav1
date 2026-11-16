<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BkuBendaharaPengeluaran extends Model
{
    public const JENIS_PAJAK = [
        'Pajak Pertambahan Nilai',
        'Pajak Penghasilan Ps 22',
        'PPH 21',
        'Belanja Tunjangan PPh/Tunjangan Khusus PNS',
        'Belanja Tunjangan PPh/Tunjangan Khusus PPPK',
        'Pajak Penghasilan Ps 23',
        'Pajak Penghasilan Ps 25',
        'Pajak Penghasilan Ps 26',
        'Pajak Penghasilan Ps 4 (2)',
        'Pajak Penghasilan Ps 15',
        'Pajak Daerah',
    ];

    protected $guarded = [];

    protected $casts = [
        'rincian_pajak' => 'array',
    ];

    public function getPajakItemsAttribute(): array
    {
        if ($this->rincian_pajak !== null) {
            return $this->rincian_pajak;
        }

        return $this->nominal_pajak > 0
            ? [['jenis' => $this->jenis_pajak, 'nominal' => (int) $this->nominal_pajak]]
            : [];
    }

    public function getTotalPajakAttribute(): int
    {
        return array_sum(array_column($this->pajak_items, 'nominal'));
    }
}
