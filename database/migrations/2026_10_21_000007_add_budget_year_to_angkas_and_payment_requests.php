<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['angkases', 'ppd_lss', 'ppd_gus', 'ups'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (!Schema::hasTable($name) || Schema::hasColumn($name, 'tahun_anggaran')) continue;
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedSmallInteger('tahun_anggaran')->default(now('Asia/Jakarta')->year);
                $table->index(['unit_blud_id', 'tahun_anggaran']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (!Schema::hasTable($name)) continue;
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['unit_blud_id', 'tahun_anggaran']);
                $table->dropColumn('tahun_anggaran');
            });
        }
    }
};
