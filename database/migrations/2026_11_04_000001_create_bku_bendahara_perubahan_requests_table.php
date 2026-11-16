<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bku_bendahara_perubahan_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bku_id');
            $table->unsignedBigInteger('unit_blud_id');
            $table->string('aksi', 10);
            $table->string('status', 10)->default('pending');
            $table->text('alasan');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['unit_blud_id', 'bku_id', 'aksi', 'status'], 'bku_bendahara_request_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bku_bendahara_perubahan_requests');
    }
};
