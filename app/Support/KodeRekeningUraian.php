<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KodeRekeningUraian
{
    /** Uraian dari akuns didahulukan; referensi Excel mengisi kode yang tidak ada. */
    public function withPrefix(string $prefix): array
    {
        $reference = Schema::hasTable('referensi_kode_rekenings')
            ? DB::table('referensi_kode_rekenings')->where('kode_rek', 'like', $prefix.'%')->pluck('uraian', 'kode_rek')->all()
            : [];
        $accounts = DB::table('akuns')->where('kode_rek', 'like', $prefix.'%')->pluck('uraian', 'kode_rek')->all();

        return array_replace($reference, $accounts);
    }
}
