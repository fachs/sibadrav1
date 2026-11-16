<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateChildren(false);
    }

    public function down(): void
    {
        $this->updateChildren(true);
    }

    private function updateChildren(bool $restore): void
    {
        DB::table('bku_bendahara_pengeluarans')->whereNotNull('parent_bku_id')
            ->orderBy('id')->chunkById(100, function ($children) use ($restore) {
                foreach ($children as $child) {
                    $parent = DB::table('bku_bendahara_pengeluarans')->find($child->parent_bku_id);
                    if (!$parent) {
                        continue;
                    }
                    $suffix = ' - '.$parent->uraian;
                    $uraian = (string) $child->uraian;
                    if ($restore && !str_ends_with($uraian, $suffix)) {
                        $uraian .= $suffix;
                    } elseif (!$restore && str_ends_with($uraian, $suffix)) {
                        $uraian = substr($uraian, 0, -strlen($suffix));
                    }
                    DB::table('bku_bendahara_pengeluarans')->where('id', $child->id)->update([
                        'kode_rekening' => $restore ? $parent->kode_rekening : null,
                        'uraian' => $uraian,
                    ]);
                }
            });
    }
};
