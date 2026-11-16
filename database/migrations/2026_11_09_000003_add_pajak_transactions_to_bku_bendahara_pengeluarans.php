<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bku_bendahara_pengeluarans', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_bku_id')->nullable()->index();
            $table->string('pajak_entry_type')->nullable();
            $table->unsignedInteger('pajak_urutan')->nullable();
        });

        DB::table('bku_bendahara_pengeluarans')->whereNull('parent_bku_id')
            ->where('nominal_pajak', '>', 0)->orderBy('id')->chunkById(100, function ($parents) {
                foreach ($parents as $parent) {
                    $pajaks = $parent->rincian_pajak !== null
                        ? json_decode($parent->rincian_pajak, true)
                        : [['jenis' => $parent->jenis_pajak, 'nominal' => $parent->nominal_pajak]];
                    foreach ($pajaks ?: [] as $index => $pajak) {
                        $nominal = (int) ($pajak['nominal'] ?? 0);
                        if ($nominal <= 0) {
                            continue;
                        }
                        $jenis = ($pajak['jenis'] ?? null) ?: 'Pajak';
                        foreach (['pemotongan', 'penyetoran'] as $offset => $type) {
                            DB::table('bku_bendahara_pengeluarans')->insert([
                                'unit_blud_id' => $parent->unit_blud_id,
                                'parent_bku_id' => $parent->id,
                                'pajak_entry_type' => $type,
                                'pajak_urutan' => $index * 2 + $offset + 1,
                                'tanggal' => $parent->tanggal,
                                'kode_rekening' => $parent->kode_rekening,
                                'subkegiatan' => $parent->subkegiatan,
                                'uraian' => ucfirst($type).' '.$jenis.' - '.$parent->uraian,
                                'penerimaan' => $type === 'penyetoran' ? $nominal : 0,
                                'pengeluaran' => $type === 'pemotongan' ? $nominal : 0,
                                'saldo' => 0,
                                'giro' => 0,
                                'created_at' => $parent->created_at,
                                'updated_at' => $parent->updated_at,
                            ]);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('bku_bendahara_pengeluarans')->whereNotNull('parent_bku_id')->delete();
        Schema::table('bku_bendahara_pengeluarans', function (Blueprint $table) {
            $table->dropIndex(['parent_bku_id']);
            $table->dropColumn(['parent_bku_id', 'pajak_entry_type', 'pajak_urutan']);
        });
    }
};
