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
        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_blud');
            $table->string('nama_unit');
            $table->string('tahun_anggaran');
            $table->string('program');
            $table->string('kegiatan');
            $table->string('subkegiatan');
            $table->string('nama_barjas');
            $table->integer('jumlah');
            $table->integer('harga_satuan');
            $table->integer('jumlah_harga');
            $table->string('satuan');
            $table->string('ruang');
            $table->string('keterangan');
            $table->string('sumber_dana');
            $table->timestamps();

            // relasi ke tabel akuns
            $table->foreignId('akun_id')->constrained('akuns')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rencana_kebutuhans');
    }
};
