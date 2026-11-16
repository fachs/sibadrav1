<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_pembiayaans', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_anggaran')->default((int) config('rba.tahun_anggaran', 2027));
            $table->index(['nama_blud', 'tahun_anggaran'], 'pembiayaan_blud_tahun_index');
        });
    }

    public function down(): void
    {
        Schema::table('rencana_pembiayaans', function (Blueprint $table) {
            $table->dropIndex('pembiayaan_blud_tahun_index');
            $table->dropColumn('tahun_anggaran');
        });
    }
};
