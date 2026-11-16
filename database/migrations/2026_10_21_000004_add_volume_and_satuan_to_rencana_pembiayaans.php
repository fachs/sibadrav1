<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_pembiayaans', function (Blueprint $table) {
            $table->string('kode_rek_perubahan')->nullable();
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('harga_satuan_perubahan', 15, 2)->nullable();
            for ($i = 1; $i <= 4; $i++) {
                $table->decimal('volume_'.$i, 15, 4)->default(1);
                $table->string('satuan_'.$i, 100)->nullable();
                $table->decimal('volume_'.$i.'_perubahan', 15, 4)->nullable();
                $table->string('satuan_'.$i.'_perubahan', 100)->nullable();
            }
        });
        DB::transaction(function () {
            DB::table('rencana_pembiayaans')->update(['harga_satuan' => DB::raw('jumlah'), 'satuan_1' => 'Paket']);
            $proposed = ['harga_satuan_perubahan' => DB::raw('jumlah_perubahan'), 'satuan_1_perubahan' => 'Paket'];
            for ($i = 1; $i <= 4; $i++) $proposed['volume_'.$i.'_perubahan'] = 1;
            DB::table('rencana_pembiayaans')->whereNotNull('jumlah_perubahan')->update($proposed);
            // These legacy codes have an unambiguous equivalent in the new reference.
            foreach (['6.1.01' => '6.1.01.01.01.0001', '6.1.02' => '6.1.02.01.01.0001',
                '6.2.01' => '6.2.03.01.01.0001', '6.2.02' => '6.2.02.01.01.0001'] as $old => $new) {
                DB::table('rencana_pembiayaans')->where('kode_rek', $old)->update(['kode_rek' => $new]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('rencana_pembiayaans', function (Blueprint $table) {
            $columns = ['kode_rek_perubahan', 'harga_satuan', 'harga_satuan_perubahan'];
            for ($i = 1; $i <= 4; $i++) {
                array_push($columns, 'volume_'.$i, 'satuan_'.$i, 'volume_'.$i.'_perubahan', 'satuan_'.$i.'_perubahan');
            }
            $table->dropColumn($columns);
        });
    }
};
