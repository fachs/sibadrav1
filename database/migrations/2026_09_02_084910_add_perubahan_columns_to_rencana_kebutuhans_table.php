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
        Schema::table('rencana_kebutuhans', function (Blueprint $table) {
            $table->string('uraian_perubahan')->nullable();
            $table->integer('jumlah_perubahan')->nullable();
            $table->string('satuan_perubahan')->nullable();
            $table->integer('harga_satuan_perubahan')->nullable();
            $table->bigInteger('jumlah_harga_perubahan')->nullable();
            $table->string('keterangan_perubahan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rencana_kebutuhans', function (Blueprint $table) {
            $table->dropColumn([
                'uraian_perubahan',
                'jumlah_perubahan',
                'satuan_perubahan',
                'harga_satuan_perubahan',
                'jumlah_harga_perubahan',
                'keterangan_perubahan'
            ]);
        });
    }
};
