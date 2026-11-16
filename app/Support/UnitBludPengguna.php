<?php

namespace App\Support;

use App\Models\UnitBlud;
use Illuminate\Validation\ValidationException;

trait UnitBludPengguna
{
    private function unitBludPengguna(): ?int
    {
        return UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) auth()->user()->upt))])->value('id');
    }

    private function wajibUnitBludPengguna(): int
    {
        $unitId = $this->unitBludPengguna();
        if ($unitId === null) {
            throw ValidationException::withMessages([
                'unit_blud_id' => 'Unit BLUD pengguna belum terdaftar. Data tidak dapat disimpan.',
            ]);
        }

        return $unitId;
    }

    private function batasiUnitBlud($query, string $column = 'unit_blud_id')
    {
        $unitId = $this->unitBludPengguna();

        return $unitId === null ? $query->whereRaw('1 = 0') : $query->where($column, $unitId);
    }
}
