<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referensi_kode_rekenings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('akun')->nullable();
            $table->unsignedSmallInteger('kelompok')->nullable();
            $table->unsignedSmallInteger('jenis')->nullable();
            $table->unsignedSmallInteger('objek')->nullable();
            $table->unsignedSmallInteger('rincian_objek')->nullable();
            $table->string('kode_rek', 30)->unique();
            $table->string('uraian');
            $table->timestamps();
        });

        $rows = json_decode(file_get_contents(database_path('data/referensi_kode_rekening.json')), true, 512, JSON_THROW_ON_ERROR);
        $timestamp = now();
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('referensi_kode_rekenings')->insert(array_map(
                fn (array $row) => $row + ['created_at' => $timestamp, 'updated_at' => $timestamp],
                $chunk
            ));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referensi_kode_rekenings');
    }
};
