<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data lama memakai kolom yang tidak lagi menjadi bagian dari laporan transaksi.
        DB::table('transaksi_penerimaans')->delete();

        $columns = ['nama_petugas', 'nama_pasien', 'poli', 'ruangan'];
        $existingColumns = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('transaksi_penerimaans', $column)));

        if ($existingColumns) {
            Schema::table('transaksi_penerimaans', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('transaksi_penerimaans', function (Blueprint $table) {
            $table->string('nama_petugas')->nullable();
            $table->string('nama_pasien')->nullable();
            $table->string('poli')->nullable();
            $table->string('ruangan')->nullable();
        });
    }
};
