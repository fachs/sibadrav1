<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spm_targets', function (Blueprint $table) {
            $table->id();
            $table->string('nama_blud');
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedTinyInteger('nomor');
            $table->string('indikator');
            $table->decimal('target_sasaran', 18, 2)->default(0);
            $table->decimal('target_capaian', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['nama_blud', 'tahun_anggaran', 'nomor']);
            $table->index(['nama_blud', 'tahun_anggaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spm_targets');
    }
};
