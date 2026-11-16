<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->json('jenis_pelayanan_perubahan')->nullable();
            $table->decimal('jumlah_perubahan', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->dropColumn('jenis_pelayanan_perubahan');
            $table->double('jumlah_perubahan')->nullable()->change();
        });
    }
};
