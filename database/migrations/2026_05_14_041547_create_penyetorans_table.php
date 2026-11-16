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
        Schema::create('penyetorans', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('nomor_bukti', 100)->unique();
            $table->decimal('jumlah', 18, 2);
            $table->text('keterangan')->nullable();

            $table->foreignId('akun_id')->constrained('akuns')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('penerimaan_id')->constrained('penerimaans')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnUpdate()->restrictOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penyetorans');
    }
};
