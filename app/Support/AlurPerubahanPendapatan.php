<?php

namespace App\Support;

use App\Models\RencanaPendapatan;
use App\Models\User;

class AlurPerubahanPendapatan
{
    public static function isSuperuser(User $user): bool
    {
        return $user->hasRole('superuser');
    }

    public static function isDinas(User $user): bool
    {
        return $user->hasAnyRole(['dinas', 'dinas kesehatan'])
            || strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
    }

    public static function prefix(string $code): string
    {
        return implode('.', array_slice(explode('.', $code), 0, 3));
    }

    public static function lintasKlasifikasi(RencanaPendapatan $record): bool
    {
        return self::prefix($record->rekening_awal ?? $record->kode_rek)
            !== self::prefix($record->rekening_akhir ?? $record->kode_rek);
    }

    public static function verifikator(RencanaPendapatan $record): string
    {
        return strtolower(trim((string) ($record->verifikator_perubahan ?: 'kepala puskesmas')));
    }

    public static function canReview(User $user, RencanaPendapatan $record): bool
    {
        if ($record->status_perubahan !== 'pending') {
            return false;
        }
        if (self::isSuperuser($user)) {
            return true;
        }
        if (self::verifikator($record) === 'dinas kesehatan') {
            return self::isDinas($user);
        }

        return $user->hasRole('kepala puskesmas')
            && $user->upt === $record->nama_blud;
    }
}
