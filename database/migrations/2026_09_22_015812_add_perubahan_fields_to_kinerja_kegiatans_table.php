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
        Schema::table('kinerja_kegiatans', function (Blueprint $table) {
            $table->text('capaian_program_tolak_ukur_perubahan')->nullable();
            $table->string('capaian_program_target_perubahan')->nullable();
            $table->text('masukan_tolak_ukur_perubahan')->nullable();
            $table->string('masukan_target_perubahan')->nullable();
            $table->text('keluaran_tolak_ukur_perubahan')->nullable();
            $table->string('keluaran_target_perubahan')->nullable();
            $table->string('status_perubahan')->nullable();
            $table->string('verifikator_perubahan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kinerja_kegiatans', function (Blueprint $table) {
            $table->dropColumn([
                'capaian_program_tolak_ukur_perubahan',
                'capaian_program_target_perubahan',
                'masukan_tolak_ukur_perubahan',
                'masukan_target_perubahan',
                'keluaran_tolak_ukur_perubahan',
                'keluaran_target_perubahan',
                'status_perubahan',
                'verifikator_perubahan',
            ]);
        });
    }
};
