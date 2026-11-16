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
        Schema::create('penerimaans', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_anggaran', 50);
            $table->string('periode', 50);
            $table->date('tanggal');
            $table->string('nomor', 100)->unique();
            $table->string('cara_pembayaran', 100);
            $table->string('kode_rekening', 100);
            $table->decimal('jumlah', 15, 2);
            $table->text('uraian');

            $table->foreignId('akun_id')->constrained('akuns')->onDelete('cascade');
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->onDelete('cascade');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penerimaans');
    }
};
