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
            $table->string('nomor_tbp', 100)->nullable()->after('nomor_terima');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penerimaans', function (Blueprint $table) {
            $table->renameColumn('tanggal_terima', 'tanggal');
            $table->renameColumn('nomor_terima', 'nomor');
            $table->dropColumn('nomor_tbp');
        });
    }
};
