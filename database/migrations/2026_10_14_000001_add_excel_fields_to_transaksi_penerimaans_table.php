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
        Schema::table('transaksi_penerimaans', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi_penerimaans', 'nomor_transaksi')) {
                $table->string('nomor_transaksi')->nullable()->after('nama_petugas');
            }

            if (!Schema::hasColumn('transaksi_penerimaans', 'ruangan')) {
                $table->string('ruangan')->nullable()->after('poli');
            }

            if (!Schema::hasColumn('transaksi_penerimaans', 'nama_penjamin')) {
                $table->string('nama_penjamin')->nullable()->after('ruangan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksi_penerimaans', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('transaksi_penerimaans', 'nama_penjamin')) {
                $columns[] = 'nama_penjamin';
            }

            if (Schema::hasColumn('transaksi_penerimaans', 'ruangan')) {
                $columns[] = 'ruangan';
            }

            if (Schema::hasColumn('transaksi_penerimaans', 'nomor_transaksi')) {
                $columns[] = 'nomor_transaksi';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
