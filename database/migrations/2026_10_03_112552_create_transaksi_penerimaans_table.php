<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaksi_penerimaans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_id')->constrained('penerimaans')->onDelete('cascade');
            $table->date('tanggal');
            $table->string('nama_pasien');
            $table->string('nama_petugas');
            $table->string('poli');
            $table->string('jenis_jasa');
            $table->string('metode_pembayaran');
            $table->decimal('total_biaya', 15, 2);
            $table->string('status')->default('Selesai');
            $table->timestamps();
        });

        // Drop the 'jumlah' column from penerimaans, and keep other fields for header
        Schema::table('penerimaans', function (Blueprint $table) {
            $table->dropColumn('jumlah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penerimaans', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->default(0);
        });

        Schema::dropIfExists('transaksi_penerimaans');
    }
};
