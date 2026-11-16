<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kepala');
            $table->string('kasubbag_tu');
            $table->string('kontak');
            $table->text('alamat');
            $table->timestamps();

            // $table->foreignId('kepala_id')->constrained('users')->cascadeOnDelete();
            // $table->foreignId('kasubbag_tu_id')->constrained('users')->cascadeOnDelete();
            // $table->foreignId('bendahara_id')->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_bluds');
    }
};
