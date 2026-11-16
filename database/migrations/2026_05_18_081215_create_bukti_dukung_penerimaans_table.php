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
        Schema::create('bukti_dukung_penerimaans', function (Blueprint $table) {
            $table->id();
            $table->string('periode', 20);
            $table->text('link');
            $table->string('nama_blud', 255);
            $table->string('tahun_anggaran', 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bukti_dukung_penerimaans');
    }
};