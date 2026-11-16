<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('angkases', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_belanja', 255);
            $table->integer('jumlah');
            $table->string('bulan', 20);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('angkases');
    }
};