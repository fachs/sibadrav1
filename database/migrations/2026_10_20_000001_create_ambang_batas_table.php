<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambang_batas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_blud');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('anggaran', 15, 2);
            $table->decimal('realisasi', 15, 2);
            $table->timestamps();
            $table->unique(['nama_blud', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambang_batas');
    }
};
