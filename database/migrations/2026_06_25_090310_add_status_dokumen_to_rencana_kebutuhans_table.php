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
        Schema::table('rencana_kebutuhans', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('sumber_dana');
            $table->string('dokumen')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rencana_kebutuhans', function (Blueprint $table) {
            $table->dropColumn(['status', 'dokumen']);
        });
    }
};
