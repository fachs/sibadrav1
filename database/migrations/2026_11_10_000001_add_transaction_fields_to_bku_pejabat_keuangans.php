<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bku_pejabat_keuangans', function (Blueprint $table) {
            $table->string('subkegiatan')->nullable();
            $table->string('jenis_pajak')->nullable();
            $table->bigInteger('nominal_pajak')->default(0);
            $table->json('rincian_pajak')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bku_pejabat_keuangans', function (Blueprint $table) {
            $table->dropColumn(['subkegiatan', 'jenis_pajak', 'nominal_pajak', 'rincian_pajak']);
        });
    }
};
