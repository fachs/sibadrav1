<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('penerimaans') && Schema::hasColumn('penerimaans', 'nomor_terima')) {
            DB::statement('ALTER TABLE `penerimaans` MODIFY `nomor_terima` VARCHAR(100) NULL');
        }

        if (Schema::hasTable('transaksi_penerimaans') && ! Schema::hasColumn('transaksi_penerimaans', 'nomor_tbp')) {
            Schema::table('transaksi_penerimaans', function (Blueprint $table) {
                $table->string('nomor_tbp', 100)->nullable()->after('nomor_transaksi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transaksi_penerimaans') && Schema::hasColumn('transaksi_penerimaans', 'nomor_tbp')) {
            Schema::table('transaksi_penerimaans', function (Blueprint $table) {
                $table->dropColumn('nomor_tbp');
            });
        }
    }
};
