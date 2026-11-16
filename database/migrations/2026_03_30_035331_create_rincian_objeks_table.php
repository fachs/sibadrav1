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
        Schema::create('rincian_objeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rencana_kebutuhan_id')->constrained('rencana_kebutuhans')->onDelete('cascade');
            $table->integer('koefisien');
            $table->string('satuan');
            $table->integer('harga');
            $table->integer('ppn');
            $table->integer('jumlah');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rincian_objeks');
    }
};
