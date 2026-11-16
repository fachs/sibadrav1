<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_anggaran')->default((int) config('rba.tahun_anggaran', 2027));
            $table->dropUnique('pendapatan_blud_rekening_unique');
            $table->unique(['nama_blud', 'tahun_anggaran', 'kode_rek', 'is_hasil_perubahan'], 'pendapatan_blud_tahun_rekening_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->unique(['nama_blud', 'kode_rek', 'is_hasil_perubahan'], 'pendapatan_blud_rekening_unique');
            $table->dropUnique('pendapatan_blud_tahun_rekening_unique');
            $table->dropColumn('tahun_anggaran');
        });
    }
};
