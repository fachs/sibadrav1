<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bku_bendahara_pengeluarans', function (Blueprint $table) {
            $table->unsignedBigInteger('spd_ls_id')->nullable()->after('id');
        });
    }

    public function down()
    {
        Schema::table('bku_bendahara_pengeluarans', function (Blueprint $table) {
            $table->dropColumn('spd_ls_id');
        });
    }
};
