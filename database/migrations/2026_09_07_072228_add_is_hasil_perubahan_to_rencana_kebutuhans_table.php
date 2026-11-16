<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan flag is_hasil_perubahan untuk menandai record yang dibuat
     * sebagai "tujuan" dalam proses pergeseran lintas akun (perubahan RBA).
     * Record ini tidak ditampilkan di halaman RBA Belanja biasa (murni),
     * hanya muncul di halaman RBA Belanja Perubahan.
     */
    public function up(): void
    {
        Schema::table('rencana_kebutuhans', function (Blueprint $table) {
            $table->boolean('is_hasil_perubahan')
                  ->default(false)
                  ->after('rekening_akhir')
                  ->comment('True jika record ini dibuat sebagai tujuan pergeseran lintas akun');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rencana_kebutuhans', function (Blueprint $table) {
            $table->dropColumn('is_hasil_perubahan');
        });
    }
};
