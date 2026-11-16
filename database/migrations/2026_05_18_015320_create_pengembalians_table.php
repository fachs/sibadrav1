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
        Schema::create('pengembalians', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('nomor');
            $table->string('jenis_bukti');
            $table->string('dasar_pembayaran');
            $table->text('peruntukan');
            $table->string('no_reg_spm');
            $table->string('pihak_ketiga');
            $table->text('penolakan');

            $table->foreignId('akun_id')->constrained('akuns')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('penerimaan_id')->constrained('penerimaans')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnUpdate()->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengembalians');
    }
};
