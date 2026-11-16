<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ups', function (Blueprint $table) {
            $table->string('bank_asal', 100)->nullable();
            $table->string('bank_tujuan', 100)->nullable();
            $table->string('npwp', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ups', function (Blueprint $table) {
            $table->dropColumn(['bank_asal', 'bank_tujuan', 'npwp']);
        });
    }
};
