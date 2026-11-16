<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->string('rekening_awal')->nullable();
            $table->string('rekening_akhir')->nullable();
            $table->uuid('pergeseran_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('rencana_pendapatans', function (Blueprint $table) {
            $table->dropIndex(['pergeseran_id']);
            $table->dropColumn(['rekening_awal', 'rekening_akhir', 'pergeseran_id']);
        });
    }
};
