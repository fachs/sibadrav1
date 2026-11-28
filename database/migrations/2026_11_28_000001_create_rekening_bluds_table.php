<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rekening_bluds')) {
            return;
        }

        Schema::create('rekening_bluds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnDelete();
            $table->string('nama_blud');
            $table->string('jenis', 100);
            $table->string('nama_bank', 150);
            $table->string('nomor_rekening', 100);
            $table->decimal('saldo', 18, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_blud_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekening_bluds');
    }
};
