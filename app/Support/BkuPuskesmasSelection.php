<?php

namespace App\Support;

use App\Models\UnitBlud;
use App\Models\BkuBendaharaPerubahanRequest;
use Illuminate\Http\Request;

trait BkuPuskesmasSelection
{
    private function isDinasBkuViewer(): bool
    {
        return auth()->user()->isDinasKesehatan();
    }

    private function puskesmasOptions()
    {
        return UnitBlud::whereRaw('LOWER(nama) LIKE ?', ['%puskesmas%'])->orderBy('nama')->get();
    }

    private function pendingKepalaBkuCount(?UnitBlud $unit): int
    {
        if (!$unit || $this->isDinasBkuViewer() || !auth()->user()->hasRole('Kepala Puskesmas')) {
            return 0;
        }

        return BkuBendaharaPerubahanRequest::with('bku')
            ->where('unit_blud_id', $unit->id)->where('status', 'pending')
            ->whereHas('bku', fn ($query) => TahunAnggaran::dates($query))
            ->get()->filter(fn ($item) => $item->verifikatorSaatIni() === BkuBendaharaPerubahanRequest::KEPALA_PUSKESMAS)
            ->count();
    }

    private function selectedBkuPuskesmas(Request $request): ?UnitBlud
    {
        if (!$this->isDinasBkuViewer()) {
            return UnitBlud::find($this->unitBludPengguna());
        }

        $id = $request->query('unit_blud_id');
        if ($id === null || $id === '') {
            return null;
        }

        abort_unless(ctype_digit((string) $id), 404);

        return UnitBlud::whereKey((int) $id)
            ->whereRaw('LOWER(nama) LIKE ?', ['%puskesmas%'])->firstOrFail();
    }
}
