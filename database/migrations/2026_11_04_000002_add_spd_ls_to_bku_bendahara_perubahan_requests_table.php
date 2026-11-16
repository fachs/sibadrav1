<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bku_bendahara_perubahan_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('bku_id')->nullable()->change();
            $table->unsignedBigInteger('spd_ls_id')->nullable()->after('bku_id');
            $table->index(['unit_blud_id', 'spd_ls_id', 'aksi', 'status'], 'bku_bendahara_rincian_request_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('bku_bendahara_perubahan_requests', function (Blueprint $table) {
            $table->dropIndex('bku_bendahara_rincian_request_lookup');
            $table->dropColumn('spd_ls_id');
            $table->unsignedBigInteger('bku_id')->nullable(false)->change();
        });
    }
};
