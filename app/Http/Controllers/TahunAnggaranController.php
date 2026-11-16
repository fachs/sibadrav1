<?php

namespace App\Http\Controllers;

use App\Support\TahunAnggaran;
use Illuminate\Http\Request;

class TahunAnggaranController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'in:'.implode(',', TahunAnggaran::options())],
            'kembali' => ['nullable', 'string', 'max:4096'],
        ]);
        $destination = $data['kembali'] ?? '/';
        $parts = parse_url($destination);
        if ($parts === false || !str_starts_with($destination, '/') || str_starts_with($destination, '//')
            || preg_match('/[\\\\\x00-\x1f\x7f]/', $destination) || isset($parts['host']) || isset($parts['scheme'])) {
            $parts = ['path' => '/'];
        }
        parse_str($parts['query'] ?? '', $query);
        foreach (['tahun', 'tahun_anggaran', 'minggu', 'page'] as $key) unset($query[$key]);
        $destination = ($parts['path'] ?? '/').($query === [] ? '' : '?'.http_build_query($query));
        $request->session()->put(TahunAnggaran::sessionKey($request), (int) $data['tahun']);

        return redirect()->to($destination);
    }
}
