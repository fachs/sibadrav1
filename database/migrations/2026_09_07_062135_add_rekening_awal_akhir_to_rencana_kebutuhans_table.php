<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tambahkan kolom rekening_awal dan rekening_akhir untuk menyimpan
     * kode rekening sumber dan tujuan saat pengajuan perubahan lintas akun.
     * Digunakan untuk menentukan verifikator (Kepala Puskesmas vs Dinas Kesehatan)
     * berdasarkan perbedaan prefix klasifikasi (5.1.01, 5.1.02, 5.2).
     */
    public function up(): void
    {
        Schema::table('rencana_kebutuhans', function (Blueprint $table) {
            $table->string('rekening_awal')->nullable()->after('verifikator_perubahan')
                  ->comment('Kode rekening sumber saat pengajuan perubahan');
            $table->string('rekening_akhir')->nullable()->after('rekening_awal')
                  ->comment('Kode rekening tujuan saat pengajuan perubahan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rencana_kebutuhans', function (Blueprint $table) {
            $table->dropColumn(['rekening_awal', 'rekening_akhir']);
        });
    }
};
