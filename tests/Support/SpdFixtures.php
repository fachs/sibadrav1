<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SpdFixtures
{
    private function createSpdTables(): void
    {
        foreach (['ls', 'gu'] as $kind) {
            Schema::create('ppd_'.$kind.'s', function (Blueprint $table) use ($kind) {
                $table->id(); $table->unsignedBigInteger('unit_blud_id')->nullable();
                $table->decimal('jumlah', 15, 2)->default(0);
                if ($kind === 'ls') {
                    $table->string('kode_rekening')->nullable(); $table->text('uraian')->nullable();
                }
                $table->timestamps(); $table->softDeletes();
            });
            Schema::create('opd_'.$kind.'s', function (Blueprint $table) use ($kind) {
                $table->id(); $table->unsignedBigInteger('unit_blud_id')->nullable();
                $table->unsignedBigInteger('ppd_'.$kind.'_id')->nullable();
                $table->decimal('jumlah', 15, 2)->default(0);
                $table->timestamps(); $table->softDeletes();
            });
            Schema::create('spd_'.$kind.'s', function (Blueprint $table) use ($kind) {
                $table->id(); $table->unsignedBigInteger('unit_blud_id')->nullable();
                $table->unsignedBigInteger('opd_'.$kind.'_id')->nullable();
                $table->string('nomor')->nullable(); $table->string('kepada')->nullable();
                $table->string('tahun_anggaran')->nullable(); $table->date('tanggal_dba')->nullable();
                $table->decimal('jumlah', 15, 2)->default(0); $table->decimal('potongan', 15, 2)->default(0);
                $table->decimal('jumlah_akhir', 15, 2)->default(0);
                $table->timestamps(); $table->softDeletes();
            });
        }
    }

    private function spdPayment($amount, string $date, array $overrides = [], string $kind = 'ls'): int
    {
        $unit = array_key_exists('unit_blud_id', $overrides) ? $overrides['unit_blud_id'] : 1;
        $parentData = ['unit_blud_id' => $unit, 'jumlah' => $amount];
        if ($kind === 'ls') {
            $parentData += ['kode_rekening' => array_key_exists('kode_rekening', $overrides) ? $overrides['kode_rekening'] : '5.1.02.01.01.0001',
                'uraian' => 'Belanja LS'];
        }
        $ppd = DB::table('ppd_'.$kind.'s')->insertGetId($parentData);
        $opd = DB::table('opd_'.$kind.'s')->insertGetId(['unit_blud_id' => $unit, 'ppd_'.$kind.'_id' => $ppd, 'jumlah' => $amount]);
        unset($overrides['kode_rekening']);

        return DB::table('spd_'.$kind.'s')->insertGetId(array_replace([
            'unit_blud_id' => $unit, 'opd_'.$kind.'_id' => $opd, 'tahun_anggaran' => substr($date, 0, 4),
            'tanggal_dba' => $date, 'created_at' => $date.' 12:00:00', 'nomor' => strtoupper($kind).'-'.$opd,
            'jumlah' => $amount, 'jumlah_akhir' => $amount,
        ], $overrides));
    }
}
