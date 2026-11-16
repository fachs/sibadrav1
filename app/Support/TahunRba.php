<?php

namespace App\Support;

use Illuminate\Http\Request;

class TahunRba
{
    public static function selected(?Request $request = null): int
    {
        return TahunAnggaran::selected($request, (int) config('rba.tahun_anggaran'));
    }
}
