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
        Schema::create('kinerja_kegiatans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->onDelete('cascade');
            $table->string('tahun');
            $table->text('capaian_program_tolak_ukur')->nullable();
            $table->string('capaian_program_target')->nullable();
            $table->text('masukan_tolak_ukur')->nullable();
            $table->string('masukan_target')->nullable();
            $table->text('keluaran_tolak_ukur')->nullable();
            $table->string('keluaran_target')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kinerja_kegiatans');
    }
};
