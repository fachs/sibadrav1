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
        Schema::table('ppd_lss', function (Blueprint $table) {
            $table->string('kode_rekening')->nullable()->after('jumlah');
            $table->string('uraian')->nullable()->after('kode_rekening');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppd_lss', function (Blueprint $table) {
            $table->dropColumn(['kode_rekening', 'uraian']);
        });
    }
};
