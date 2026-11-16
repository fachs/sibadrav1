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
        Schema::table('penerimaans', function (Blueprint $table) {
            $table->string('verif_pk', 100)->default('pending')->after('unit_blud_id');
            $table->string('verif_dinas', 100)->default('pending')->after('verif_pk');
            $table->string('tanggal_setor')->nullable()->after('verif_dinas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penerimaans', function (Blueprint $table) {
            $table->dropColumn(['verif_pk', 'verif_dinas', 'tanggal_setor']);
        });
    }
};
