<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hentikan sebelum mengubah data jika rekening lama perlu digabungkan dahulu.
        $duplicates = DB::table('rencana_pendapatans')
            ->select('nama_blud', 'kode_rek')->selectRaw('COALESCE(is_hasil_perubahan, 0) AS jenis')
            ->groupBy('nama_blud', 'kode_rek')->groupByRaw('COALESCE(is_hasil_perubahan, 0)')
            ->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Terdapat rekening pendapatan ganda per BLUD. Gabungkan data sebelum menjalankan migrasi rincian pelayanan.');
        }

        Schema::create('jenis_pelayanan_pendapatans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rencana_pendapatan_id')->constrained('rencana_pendapatans')->cascadeOnDelete();
            $table->string('unit_pelayanan');
            $table->string('uraian');
            for ($i = 1; $i <= 4; $i++) {
                $table->decimal('volume_'.$i, 15, 4)->default(1);
                $table->string('satuan_'.$i, 100)->nullable();
            }
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('jumlah', 15, 2);
            $table->boolean('is_data_lama')->default(false);
            $table->timestamps();
        });

        DB::transaction(function () {
            DB::table('rencana_pendapatans')->whereNull('is_hasil_perubahan')->update(['is_hasil_perubahan' => false]);
            DB::table('rencana_pendapatans')->orderBy('id')->chunkById(200, function ($records) {
                foreach ($records as $record) {
                    DB::table('jenis_pelayanan_pendapatans')->insert([
                        'rencana_pendapatan_id' => $record->id,
                        'unit_pelayanan' => 'Belum ditentukan',
                        'uraian' => $record->uraian,
                        'volume_1' => 1, 'satuan_1' => 'Paket',
                        'harga_satuan' => $record->jumlah, 'jumlah' => $record->jumlah,
                        'is_data_lama' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        });
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->change();
            $table->unique(['nama_blud', 'kode_rek', 'is_hasil_perubahan'], 'pendapatan_blud_rekening_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->dropUnique('pendapatan_blud_rekening_unique');
            $table->double('jumlah')->change();
        });
        Schema::dropIfExists('jenis_pelayanan_pendapatans');
    }
};
