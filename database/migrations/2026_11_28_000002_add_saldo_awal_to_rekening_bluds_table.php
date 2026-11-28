<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rekening_bluds')) {
            return;
        }

        if (! Schema::hasColumn('rekening_bluds', 'saldo_awal')) {
            Schema::table('rekening_bluds', function (Blueprint $table) {
                $table->decimal('saldo_awal', 18, 2)->default(0)->after('nomor_rekening');
            });

            DB::table('rekening_bluds')->update([
                'saldo_awal' => DB::raw('saldo'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rekening_bluds') && Schema::hasColumn('rekening_bluds', 'saldo_awal')) {
            Schema::table('rekening_bluds', function (Blueprint $table) {
                $table->dropColumn('saldo_awal');
            });
        }
    }
};
