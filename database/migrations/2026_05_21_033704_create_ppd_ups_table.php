<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppd_ups', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 100)->unique();
            $table->integer('jumlah');
            $table->string('nama_bendahara', 255);
            $table->string('no_rek', 100);
            $table->string('no_sk', 100);
            $table->date('tanggal_sk');
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppd_ups');
    }
};