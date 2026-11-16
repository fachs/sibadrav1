<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opd_ups', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 100)->unique();
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nomor_dba', 100);
            $table->date('tanggal_dba');
            $table->foreignId('ppd_up_id')->constrained('ppd_ups')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('tahun_anggaran', 10);
            $table->string('bank_asal', 100);
            $table->integer('jumlah');
            $table->string('kepada', 255);
            $table->string('npwp', 100)->nullable();
            $table->string('no_rek_tujuan', 100);
            $table->string('bank_tujuan', 100);
            $table->text('peruntukan')->nullable();
            $table->integer('potongan')->default(0);
            $table->integer('jumlah_akhir');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opd_ups');
    }
};