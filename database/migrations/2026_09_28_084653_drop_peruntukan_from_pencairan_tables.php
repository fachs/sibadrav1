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
        $tables = [
            'ppd_lss', 'opd_lss', 'spd_lss',
            'ppd_gus', 'opd_gus', 'spd_gus',
            'ppd_ups', 'opd_ups', 'spd_ups',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'peruntukan')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('peruntukan');
                });
            }
            if (Schema::hasColumn($table, 'peruntukkan')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('peruntukkan');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed since we want to drop it completely.
    }
};
