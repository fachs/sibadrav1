<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_pelayanan_pendapatans', function (Blueprint $table) {
            $table->boolean('gunakan_cagr')->default(false);
            $table->decimal('realisasi_tahun_3', 15, 2)->nullable();
            $table->decimal('realisasi_tahun_2', 15, 2)->nullable();
            $table->decimal('realisasi_jan_jun', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('jenis_pelayanan_pendapatans', function (Blueprint $table) {
            $table->dropColumn(['gunakan_cagr', 'realisasi_tahun_3', 'realisasi_tahun_2', 'realisasi_jan_jun']);
        });
    }
};
