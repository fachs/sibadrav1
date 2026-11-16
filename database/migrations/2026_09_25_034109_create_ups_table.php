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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('spd_ups');
        Schema::dropIfExists('opd_ups');
        Schema::dropIfExists('ppd_ups');
        Schema::enableForeignKeyConstraints();

        Schema::create('ups', function (Blueprint $table) {
            $table->id();
            
            // Properti dari ppd_ups
            $table->string('nomor', 100)->unique();
            $table->integer('jumlah');
            $table->string('nama_bendahara', 255);
            $table->string('no_rek', 100);
            $table->string('no_sk', 100);
            $table->date('tanggal_sk');
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnUpdate()->restrictOnDelete();
            
            // Tambahan
            $table->string('no_ppd', 100)->nullable();
            $table->string('no_spd', 100)->nullable();
            $table->string('no_rek_tujuan', 100)->nullable();
            $table->integer('potongan')->default(0);
            $table->string('kepada', 255)->nullable();
            $table->text('peruntukkan')->nullable();
            $table->string('no_dba', 100)->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ups');
    }
};
