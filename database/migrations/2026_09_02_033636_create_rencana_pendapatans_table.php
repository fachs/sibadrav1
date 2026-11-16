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
        Schema::create('rencana_pendapatans', function (Blueprint $table) {
            $table->id();
            $table->string('kode_rek');
            $table->string('uraian');
            $table->double('jumlah');
            $table->unsignedBigInteger('user_id');
            $table->string('nama_blud');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rencana_pendapatans');
    }
};
