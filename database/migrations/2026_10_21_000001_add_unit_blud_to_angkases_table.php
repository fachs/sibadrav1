<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('angkases', function (Blueprint $table) {
            $table->foreignId('unit_blud_id')->nullable()->constrained('unit_bluds')->restrictOnDelete();
            $table->index(['unit_blud_id', 'jenis_belanja', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::table('angkases', function (Blueprint $table) {
            $table->dropIndex(['unit_blud_id', 'jenis_belanja', 'bulan']);
            $table->dropConstrainedForeignId('unit_blud_id');
        });
    }
};
