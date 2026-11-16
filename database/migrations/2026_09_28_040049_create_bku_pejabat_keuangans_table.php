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
        Schema::create('bku_pejabat_keuangans', function (Blueprint $table) {
            $table->id();
            $table->string('no_transaksi')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('kode_rekening')->nullable();
            $table->text('uraian')->nullable();
            $table->bigInteger('penerimaan')->default(0);
            $table->bigInteger('pengeluaran')->default(0);
            $table->bigInteger('saldo')->default(0);
            $table->bigInteger('giro')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bku_pejabat_keuangans');
    }
};
