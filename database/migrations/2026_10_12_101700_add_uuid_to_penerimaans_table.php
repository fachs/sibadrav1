<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('penerimaans', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Generate UUID untuk data yang sudah ada
        $penerimaans = DB::table('penerimaans')->select('id', 'uuid')->get();
        foreach ($penerimaans as $item) {
            if (empty($item->uuid)) {
                DB::table('penerimaans')
                    ->where('id', $item->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        }

        // Tambahkan unique index setelah pengisian uuid selesai
        Schema::table('penerimaans', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penerimaans', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
