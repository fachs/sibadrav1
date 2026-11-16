<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AmbangBatas extends Model
{
    protected $table = 'ambang_batas';

    protected $fillable = ['nama_blud', 'tahun', 'anggaran', 'realisasi'];

    protected $casts = ['tahun' => 'integer', 'anggaran' => 'decimal:2', 'realisasi' => 'decimal:2'];

    public function scopeForDocumentYear(Builder $query, string $upt, int $tahun): Builder
    {
        return $query->where('nama_blud', $upt)
            ->whereBetween('tahun', [$tahun - 2, $tahun - 1])
            ->orderBy('tahun');
    }

    public function getSelisihAttribute(): float
    {
        return round((float) $this->realisasi - (float) $this->anggaran, 2);
    }

    public function getPersentaseAttribute(): ?float
    {
        return (float) $this->anggaran > 0
            ? round($this->selisih / (float) $this->anggaran * 100, 2)
            : null;
    }
}
