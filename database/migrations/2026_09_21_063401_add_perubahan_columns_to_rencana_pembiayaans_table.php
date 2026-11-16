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
        Schema::table('rencana_pembiayaans', function (Blueprint $table) {
            $table->string('uraian_perubahan')->nullable();
            $table->decimal('jumlah_perubahan', 15, 2)->nullable();
            $table->string('status_perubahan')->nullable();
            $table->string('verifikator_perubahan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rencana_pembiayaans', function (Blueprint $table) {
            $table->dropColumn([
                'uraian_perubahan',
                'jumlah_perubahan',
                'status_perubahan',
                'verifikator_perubahan',
            ]);
        });
    }
};
