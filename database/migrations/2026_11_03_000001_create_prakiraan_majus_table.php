<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prakiraan_majus', function (Blueprint $table) {
            $table->id();
            $table->string('nama_blud');
            $table->unsignedSmallInteger('tahun_baseline');
            $table->string('kode_rek');
            $table->string('uraian');
            $table->decimal('nilai_baseline', 18, 2)->default(0);
            $table->decimal('nilai_tahun_1', 18, 2)->default(0);
            $table->decimal('nilai_tahun_2', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['nama_blud', 'tahun_baseline', 'kode_rek']);
            $table->index(['nama_blud', 'tahun_baseline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prakiraan_majus');
    }
};
