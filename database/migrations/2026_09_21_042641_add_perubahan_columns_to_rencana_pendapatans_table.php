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
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->string('uraian_perubahan')->nullable();
            $table->double('jumlah_perubahan')->nullable();
            $table->string('status_perubahan')->nullable();
            $table->string('verifikator_perubahan')->nullable();
            $table->boolean('is_hasil_perubahan')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->dropColumn([
                'uraian_perubahan',
                'jumlah_perubahan',
                'status_perubahan',
                'verifikator_perubahan',
                'is_hasil_perubahan'
            ]);
        });
    }
};
