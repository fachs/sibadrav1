<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bukti_dukung_penerimaans', function (Blueprint $table) {
            $table->unsignedBigInteger('penerimaan_id')->nullable()->after('tahun_anggaran');
            $table->unsignedBigInteger('unit_blud_id')->nullable()->after('penerimaan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bukti_dukung_penerimaans', function (Blueprint $table) {
            $table->dropColumn(['penerimaan_id', 'unit_blud_id']);
        });
    }
};
