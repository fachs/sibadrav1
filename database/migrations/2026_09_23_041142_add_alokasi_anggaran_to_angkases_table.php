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
        Schema::table('angkases', function (Blueprint $table) {
            $table->decimal('alokasi_anggaran', 20, 2)->default(0)->after('jenis_belanja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('angkases', function (Blueprint $table) {
            //
        });
    }
};
