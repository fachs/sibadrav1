<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bku_pejabat_keuangans', 'bku_bendahara_pengeluarans'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('unit_blud_id')->nullable()->index();
            });
        }
        // Hanya tautkan data lama yang BLUD-nya dapat dipastikan dari SPD LS.
        DB::table('bku_bendahara_pengeluarans')->whereNotNull('spd_ls_id')->orderBy('id')
            ->chunkById(100, function ($rows) {
                $units = DB::table('spd_lss')->whereIn('id', $rows->pluck('spd_ls_id'))->pluck('unit_blud_id', 'id');
                foreach ($rows as $row) {
                    if ($unit = $units->get($row->spd_ls_id)) {
                        DB::table('bku_bendahara_pengeluarans')->where('id', $row->id)->update(['unit_blud_id' => $unit]);
                    }
                }
            });
    }

    public function down(): void
    {
        foreach (['bku_pejabat_keuangans', 'bku_bendahara_pengeluarans'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['unit_blud_id']);
                $table->dropColumn('unit_blud_id');
            });
        }
    }
};
