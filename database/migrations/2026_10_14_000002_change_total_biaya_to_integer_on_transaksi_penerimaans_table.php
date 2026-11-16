<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE transaksi_penerimaans MODIFY total_biaya BIGINT UNSIGNED NOT NULL DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE transaksi_penerimaans MODIFY total_biaya DECIMAL(15, 2) NOT NULL DEFAULT 0');
    }
};
