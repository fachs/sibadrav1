<?php

namespace App\Support;

class ReferensiPendapatan
{
    public const KLASIFIKASI = [
        '4.1' => '4.1 - JASA LAYANAN',
        '4.2' => '4.2 - HIBAH',
        '4.3' => '4.3 - HASIL KERJASAMA',
        '4.4' => '4.4 - APBD',
        '4.5' => '4.5 - LAIN-LAIN PENDAPATAN BLUD YANG SAH',
    ];

    private array $rekening;

    public function __construct(?array $rekening = null)
    {
        $this->rekening = $rekening ?? array_merge(
            json_decode(file_get_contents(resource_path('data/referensi-pendapatan.json')), true, 512, JSON_THROW_ON_ERROR)['rekening'],
            json_decode(file_get_contents(resource_path('data/referensi-pendapatan-tambahan.json')), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function all(): array
    {
        return $this->rekening;
    }

    public function codes(): array
    {
        return array_column($this->rekening, 'kode_rek');
    }

    public function rekening(string $kode): ?array
    {
        foreach ($this->rekening as $rekening) {
            if ($rekening['kode_rek'] === $kode) {
                return $rekening;
            }
        }

        return null;
    }

    public function pelayanan(string $kode, string $uraian): ?array
    {
        foreach ($this->rekening($kode)['pelayanan'] ?? [] as $pelayanan) {
            if ($pelayanan['uraian'] === $uraian) {
                return $pelayanan;
            }
        }

        return null;
    }
}
