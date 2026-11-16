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
        Schema::create('ppd_lss', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 100)->unique();
            $table->integer('jumlah');
            $table->string('nama_bendahara', 255);
            $table->string('no_rek', 100);
            $table->string('no_sk', 100);
            $table->date('tanggal_sk');
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnUpdate()->restrictOnDelete();
            
            $table->text('peruntukan')->nullable();
            $table->string('bank_asal', 100)->nullable();
            $table->string('kepada', 255)->nullable();
            $table->string('npwp', 100)->nullable();
            $table->string('bank_tujuan', 100)->nullable();
            $table->string('no_rek_tujuan', 100)->nullable();
            $table->integer('potongan')->default(0);
            $table->integer('jumlah_akhir')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppd_lss');
    }
};
