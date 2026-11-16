<?php

namespace App\Support;

use Illuminate\Http\Request;

class TahunAnggaran
{
    public static function sessionKey(Request $request): string
    {
        return 'tahun_anggaran.user_'.($request->user()?->getAuthIdentifier() ?? 'guest');
    }

    public static function saved(?Request $request = null): ?int
    {
        $request ??= request();
        $year = $request->hasSession() ? $request->session()->get(self::sessionKey($request)) : null;

        return is_numeric($year) && in_array((int) $year, self::options(), true) ? (int) $year : null;
    }

    public static function selected(?Request $request = null, ?int $default = null): int
    {
        $request ??= request();
        $validated = $request->validate(['tahun' => ['nullable', 'integer', 'in:'.implode(',', self::options())]]);

        $default ??= now('Asia/Jakarta')->year;

        return self::saved($request) ?? (int) ($validated['tahun'] ?? (in_array($default, self::options(), true) ? $default : self::options()[0]));
    }

    public static function context(?Request $request = null): int
    {
        $request ??= request();
        $planning = $request->routeIs('rba*', 'renbut*', 'rencana-kebutuhan*', 'rincian*', 'dokumen-rba.*');

        return self::selected($request, $planning ? (int) config('rba.tahun_anggaran') : null);
    }

    public static function options(): array
    {
        return [2027, 2026];
    }

    public static function dates($query, string $column = 'tanggal', ?int $year = null)
    {
        $year ??= self::selected();

        return $query->where($column, '>=', $year.'-01-01')->where($column, '<', ($year + 1).'-01-01');
    }

    public static function dateRules(): array
    {
        $year = self::selected();

        return ['required', 'date', 'after_or_equal:'.$year.'-01-01', 'before:'.($year + 1).'-01-01'];
    }

    public static function inputDate(): string
    {
        return now('Asia/Jakarta')->setYear(self::selected())->format('Y-m-d');
    }
}
