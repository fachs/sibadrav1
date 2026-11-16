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
        Schema::table('ppd_gus', function (Blueprint $table) {
            $table->text('peruntukan')->nullable();
            $table->string('bank_asal', 100)->nullable();
            $table->string('kepada', 255)->nullable();
            $table->string('npwp', 100)->nullable();
            $table->string('bank_tujuan', 100)->nullable();
            $table->string('no_rek_tujuan', 100)->nullable();
            $table->integer('potongan')->default(0);
            $table->integer('jumlah_akhir')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppd_gus', function (Blueprint $table) {
            //
        });
    }
};
