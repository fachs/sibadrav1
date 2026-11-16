<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bku_bendahara_perubahan_requests', function (Blueprint $table) {
            $table->string('verifikator', 32)->default('dinas_kesehatan')->after('aksi');
        });
    }

    public function down(): void
    {
        Schema::table('bku_bendahara_perubahan_requests', function (Blueprint $table) {
            $table->dropColumn('verifikator');
        });
    }
};
