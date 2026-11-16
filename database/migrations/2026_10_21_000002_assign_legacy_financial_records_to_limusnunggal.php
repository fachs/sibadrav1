<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ownership of these existing records was confirmed by the user.
        $unitId = DB::table('unit_bluds')->whereRaw('LOWER(TRIM(nama)) = ?', ['puskesmas limusnunggal'])->value('id');
        if ($unitId === null) {
            return;
        }

        DB::transaction(function () use ($unitId) {
            foreach ([
                'bku_pejabat_keuangans' => [1],
                'bku_bendahara_pengeluarans' => [1, 2, 6],
                'angkases' => range(73, 84),
            ] as $table => $ids) {
                DB::table($table)->whereIn('id', $ids)->whereNull('unit_blud_id')->update(['unit_blud_id' => $unitId]);
            }
        });
    }

    public function down(): void
    {
        // Retain the confirmed ownership when rolling back application code.
    }
};
