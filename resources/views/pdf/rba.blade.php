<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid black; padding: 4px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .no-border { border: none !important; }
        .no-border-right { border-right: none !important; }
        .no-border-left { border-left: none !important; }
        .no-border-top { border-top: none !important; }
        .no-border-bottom { border-bottom: none !important; }
    </style>
</head>
<body>
@if($isAllBlud ?? false)
<table style="width: 100%; border: 1px solid black; margin-bottom: -1px;">
    <tr>
        <td style="width: 15%; text-align: center; border-right: none;">
            @php
                $logoPath = public_path('images/logo-pemkot.png');
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" style="width: 70px;" alt="Logo">
            @endif
        </td>
        <td style="width: 70%; text-align: center; border-right: none; border-left: none;">
            <div style="font-size: 14px; font-weight: bold;">PEMERINTAH KOTA SUKABUMI</div>
            <div style="font-size: 14px; font-weight: bold;">DINAS KESEHATAN</div>
            <div style="font-size: 16px; font-weight: bold;">REKAPITULASI RBA BELANJA SELURUH BLUD</div>
            <div style="font-size: 11px;">Kota Sukabumi</div>
        </td>
        <td style="width: 15%; border-left: none;"></td>
    </tr>
    <tr>
        <td colspan="3" class="text-center" style="border-top: 1px solid black;">
            <div style="font-size: 13px; font-weight: bold;">RINCIAN BISNIS DAN ANGGARAN (RBA)</div>
            <div style="font-size: 12px;">DATA AGREGAT BELANJA PER JENIS KODE REKENING</div>
            <div style="font-size: 12px;">TAHUN ANGGARAN {{ $tahunRba ?? $tahun ?? date('Y') }}</div>
        </td>
    </tr>
</table>

<table style="width: 100%; font-size: 10px;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th style="width: 4%; text-align: center;">No</th>
            <th style="text-align: left; width: 20%;">Nama Puskesmas / Unit BLUD</th>
            <th style="width: 12%; text-align: right;">5.1.01 - BELANJA PEGAWAI BLUD (Rp)</th>
            <th style="width: 13%; text-align: right;">5.1.02 - BELANJA BARANG DAN JASA BLUD (Rp)</th>
            <th style="width: 13%; text-align: right;">5.2.02 - BELANJA MODAL PERALATAN DAN MESIN BLUD (Rp)</th>
            <th style="width: 12%; text-align: right;">5.2.03 - BELANJA MODAL GEDUNG DAN BANGUNAN BLUD (Rp)</th>
            <th style="width: 12%; text-align: right;">5.2.04 - BELANJA MODAL JALAN, JARINGAN DAN IRIGASI BLUD (Rp)</th>
            <th style="width: 14%; text-align: right;">TOTAL BELANJA (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($agregatPuskesmas ?? [] as $idx => $row)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ $row['nama'] }}</td>
                <td class="text-right">{{ number_format($row['belanja_pegawai'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['belanja_barang_jasa'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['belanja_modal_peralatan'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['belanja_modal_gedung'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['belanja_modal_jalan'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($row['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background-color: #f2f2f2; font-weight: bold;">
            <td colspan="2" class="text-center font-bold">TOTAL KESELURUHAN</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['belanja_pegawai'] ?? 0, 0, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['belanja_barang_jasa'] ?? 0, 0, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['belanja_modal_peralatan'] ?? 0, 0, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['belanja_modal_gedung'] ?? 0, 0, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['belanja_modal_jalan'] ?? 0, 0, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['total'] ?? 0, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<div style="page-break-inside: avoid; margin-top: 30px;">
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 60%; border: none;"></td>
            <td style="width: 40%; text-align: left; border: none; padding-left: 50px;">
                Sukabumi, {{ now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}<br>
                Kepala Dinas Kesehatan Kota Sukabumi<br>
                
                <br><br><br><br>
                
                <strong>{{ $penandatangan->kepala ?? 'Nama Lengkap' }}</strong><br>
                NIP. {{ $penandatangan->nip_kapus ?? '_______________' }}
            </td>
        </tr>
    </table>
</div>
@else
<table style="width: 100%; border: 1px solid black; margin-bottom: -1px;">
    <tr>
        <td style="width: 15%; text-align: center; border-right: none;">
            @php
                $logoPath = public_path('images/logo-pemkot.png');
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" style="width: 80px;" alt="Logo">
            @endif
        </td>
        <td style="width: 70%; text-align: center; border-right: none; border-left: none;">
            <div style="font-size: 14px; font-weight: bold;">PEMERINTAH KOTA SUKABUMI</div>
            <div style="font-size: 14px; font-weight: bold;">DINAS KESEHATAN</div>
            <div style="font-size: 16px; font-weight: bold;">BLUD UPTD {{ strtoupper($uptName) }}</div>
            <div style="font-size: 12px;">{{ $penandatangan->alamat ?? '<<Alamat Puskesmas>>' }}</div>
        </td>
        <td style="width: 15%; border-left: none;"></td>
    </tr>
    <tr>
        <td colspan="3" class="text-center" style="border-top: 1px solid black;">
            <div style="font-size: 14px;">RINCIAN BISNIS DAN ANGGARAN</div>
            <div style="font-size: 14px;">ANGGARAN BELANJA</div>
            <div style="font-size: 14px;">TAHUN ANGGARAN 2027</div>
        </td>
    </tr>
</table>

<table style="width: 100%; font-size: 11px; margin-top: 10px; margin-bottom: 15px;">
    <tbody>
        <tr>
            <td class="font-bold" style="width: 25%;">Program</td>
            <td colspan="2">1.02.01 - PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA</td>
        </tr>
        <tr>
            <td class="font-bold">Kegiatan</td>
            <td colspan="2">1.02.01.2.10 - Peningkatan Pelayanan BLUD</td>
        </tr>
        <tr>
            <td class="font-bold">Sub Kegiatan</td>
            <td colspan="2">1.02.01.2.10.0001 - Pelayanan dan Penunjang Pelayanan BLUD</td>
        </tr>
        <tr>
            <td class="font-bold">Sumber Dana</td>
            <td colspan="2">BLUD</td>
        </tr>
        <tr>
            <th colspan="3" class="text-center font-bold">Indikator & Tolak Ukur Kinerja Kegiatan BLUD</th>
        </tr>
        <tr>
            <th class="text-center">Indikator</th>
            <th class="text-center" style="width: 50%;">Tolak Ukur Kinerja</th>
            <th class="text-center" style="width: 25%;">Target Kinerja</th>
        </tr>
        <tr>
            <td class="font-bold">Capaian Program</td>
            <td>{{ $kinerjaKegiatan->capaian_program_tolak_ukur ?? '' }}</td>
            <td>{{ $kinerjaKegiatan->capaian_program_target ?? '' }}</td>
        </tr>
        <tr>
            <td class="font-bold">Masukan</td>
            <td>{{ $kinerjaKegiatan->masukan_tolak_ukur ?? '' }}</td>
            <td>{{ $kinerjaKegiatan->masukan_target ?? '' }}</td>
        </tr>
        <tr>
            <td class="font-bold">Keluaran</td>
            <td>{{ $kinerjaKegiatan->keluaran_tolak_ukur ?? '' }}</td>
            <td>{{ $kinerjaKegiatan->keluaran_target ?? '' }}</td>
        </tr>
        <tr>
            <td class="font-bold">Kelompok Sasaran Kegiatan</td>
            <td colspan="2">:</td>
        </tr>
    </tbody>
</table>

@php
    $grandTotal = 0;
@endphp

<table style="width: 100%; font-size: 11px;">
    <thead>
        <tr style="background-color: #f8f9fa;">
            <th colspan="11" class="text-center font-bold">RINCIAN BISNIS DAN ANGGARAN PER SUB KEGIATAN</th>
        </tr>
        <tr style="background-color: #f8f9fa;">
            <th colspan="6" rowspan="2" class="text-center align-middle" style="vertical-align: middle;">Kode</th>
            <th rowspan="2" class="text-center align-middle" style="vertical-align: middle;">Uraian</th>
            <th colspan="4" class="text-center">Rincian Perhitungan</th>
        </tr>
        <tr style="background-color: #f8f9fa;">
            <th class="text-center">Volume</th>
            <th class="text-center">Satuan</th>
            <th class="text-center">Harga Satuan (Rp)</th>
            <th class="text-center">Jumlah (Rp)</th>
        </tr>
        <tr style="background-color: #fff;">
            <th class="text-center" colspan="6">1</th>
            <th class="text-center">2</th>
            <th class="text-center">3</th>
            <th class="text-center">4</th>
            <th class="text-center">5</th>
            <th class="text-center">6</th>
        </tr>
    </thead>

    <tbody>
        @php
            $klasifikasi = [
                'belanja_pegawai'           => ['prefix' => '5.1.01', 'label' => '5.1.01 - Belanja Pegawai BLUD',                     'color' => '#f8f9fa'],
                'belanja_barang_jasa'       => ['prefix' => '5.1.02', 'label' => '5.1.02 - Belanja Barang dan Jasa BLUD',             'color' => '#f8f9fa'],
                'belanja_modal_peralatan'   => ['prefix' => '5.2.02', 'label' => '5.2.02 - Belanja Modal Peralatan dan Mesin BLUD',   'color' => '#f8f9fa'],
                'belanja_modal_gedung'      => ['prefix' => '5.2.03', 'label' => '5.2.03 - Belanja Modal Gedung dan Bangunan BLUD',   'color' => '#f8f9fa'],
                'belanja_modal_jalan'       => ['prefix' => '5.2.04', 'label' => '5.2.04 - Belanja Modal Jalan, Jaringan dan Irigasi BLUD', 'color' => '#f8f9fa'],
            ];

            $groupedByKlasifikasi = [];
            $classifiedKeys = [];
            foreach ($klasifikasi as $key => $klas) {
                $groupedByKlasifikasi[$key] = $grouped->filter(function ($items, $akun_id) use ($klas, &$classifiedKeys) {
                    if (str_starts_with($akun_id, $klas['prefix'])) {
                        $classifiedKeys[] = $akun_id;
                        return true;
                    }
                    return false;
                });
            }
        @endphp

        @php
            $totalBelanja = $grouped->flatten()->sum('jumlah_harga');
            $totalBelanjaOperasi = ($groupedByKlasifikasi['belanja_pegawai'] ?? collect())->flatten()->sum('jumlah_harga') 
                                 + ($groupedByKlasifikasi['belanja_barang_jasa'] ?? collect())->flatten()->sum('jumlah_harga');
            $totalBelanjaModal = $grouped->filter(function ($items, $akun_id) {
                return str_starts_with($akun_id, '5.2');
            })->flatten()->sum('jumlah_harga');
        @endphp

        @if ($totalBelanja > 0)
        <tr style="background-color: #f8f9fa;">
            <td class="font-bold text-center">5</td>
            <td class="font-bold text-center"></td>
            <td class="font-bold text-center"></td>
            <td class="font-bold text-center"></td>
            <td class="font-bold text-center"></td>
            <td class="font-bold text-center"></td>
            <td class="font-bold">BELANJA</td>
            <td colspan="3"></td>
            <td class="text-right font-bold">{{ number_format($totalBelanja, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if ($totalBelanjaOperasi > 0)
        <tr style="background-color: #f8f9fa;">
            <td class="font-bold text-center">5</td>
            <td class="font-bold text-center">1</td>
            <td class="font-bold text-center"></td>
            <td class="font-bold text-center"></td>
            <td class="font-bold text-center"></td>
            <td class="font-bold text-center"></td>
            <td class="font-bold">BELANJA OPERASI</td>
            <td colspan="3"></td>
            <td class="text-right font-bold">{{ number_format($totalBelanjaOperasi, 0, ',', '.') }}</td>
        </tr>
        @endif

        @php $printed_5_2 = false; @endphp
        @foreach ($klasifikasi as $key => $klas)
            @if (str_starts_with($klas['prefix'], '5.2') && !$printed_5_2 && $totalBelanjaModal > 0)
            <tr style="background-color: #f8f9fa;">
                <td class="font-bold text-center">5</td>
                <td class="font-bold text-center">2</td>
                <td class="font-bold text-center"></td>
                <td class="font-bold text-center"></td>
                <td class="font-bold text-center"></td>
                <td class="font-bold text-center"></td>
                <td class="font-bold">BELANJA MODAL</td>
                <td colspan="3"></td>
                <td class="text-right font-bold">{{ number_format($totalBelanjaModal, 0, ',', '.') }}</td>
            </tr>
                @php $printed_5_2 = true; @endphp
            @endif
            @php
                $itemsInKlas = $groupedByKlasifikasi[$key];
                $subtotalKlas = $itemsInKlas->flatten()->sum('jumlah_harga');
                
                $prefixParts = explode('.', $klas['prefix']);
                $p1 = $prefixParts[0] ?? '';
                $p2 = $prefixParts[1] ?? '';
                $p3 = $prefixParts[2] ?? '';
                
                $label = trim(preg_replace('/^[0-9\.]+ - /', '', $klas['label']));
            @endphp

            @if ($itemsInKlas->isNotEmpty())
                {{-- HEADER KLASIFIKASI --}}
                <tr style="background-color: {{ $klas['color'] }};">
                    <td class="font-bold text-center">{{ $p1 }}</td>
                    <td class="font-bold text-center">{{ $p2 }}</td>
                    <td class="font-bold text-center">{{ $p3 }}</td>
                    <td class="font-bold text-center"></td>
                    <td class="font-bold text-center"></td>
                    <td class="font-bold text-center"></td>
                    <td class="font-bold">{{ $label }}</td>
                    <td colspan="3"></td>
                    <td class="text-right font-bold">{{ number_format($subtotalKlas, 0, ',', '.') }}</td>
                </tr>

                @foreach ($itemsInKlas as $klaster => $items)
                    @php
                        $subtotal = $items->sum('jumlah_harga');
                        $grandTotal += $subtotal;
                        $uraian = optional($items->first()->akuns)->uraian;
                        
                        $parts = explode('.', $klaster);
                        $k1 = $parts[0] ?? '';
                        $k2 = $parts[1] ?? '';
                        $k3 = $parts[2] ?? '';
                        $k4 = $parts[3] ?? '';
                        $k5 = $parts[4] ?? '';
                        $k6 = implode('.', array_slice($parts, 5));
                    @endphp

                    {{-- Header per group (Akun) --}}
                    <tr>
                        <td class="font-bold text-center">{{ $k1 }}</td>
                        <td class="font-bold text-center">{{ $k2 }}</td>
                        <td class="font-bold text-center">{{ $k3 }}</td>
                        <td class="font-bold text-center">{{ $k4 }}</td>
                        <td class="font-bold text-center">{{ $k5 }}</td>
                        <td class="font-bold text-center">{{ $k6 }}</td>
                        <td class="font-bold">{{ $uraian ?: '-' }}</td>
                        <td colspan="3"></td>
                        <td class="text-right font-bold">{{ number_format($subtotal, 0, ',', '.') }}</td>
                    </tr>

                    <!-- DATA ITEM -->
                    @foreach ($items as $item)
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>
                                {{ $item->uraian }} <br>
                                @if(!empty($item->spesifikasi))
                                    <span style="font-size: 10px; color: #555;">Spesifikasi: {{ $item->spesifikasi }}</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->jumlah }}</td>
                            <td class="text-center">{{ $item->satuan }}</td>
                            <td class="text-right">{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($item->jumlah_harga, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach

                @endforeach
            @endif
        @endforeach

        <!-- GRAND TOTAL -->
        <tr style="background-color: #f8f9fa;">
            <td colspan="10" class="text-center font-bold">JUMLAH</td>
            <td class="text-right font-bold">{{ number_format($grandTotal, 0, ',', '.') }}</td>
        </tr>

    </tbody>
</table>

<div style="page-break-inside: avoid; margin-top: 30px;">
    <table class="no-border">
        <tr>
            <td class="no-border" style="width: 70%;"></td>
            <td class="no-border" style="width: 30%; text-align: left; padding-left: 20px;">
                Sukabumi, {{ now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}<br>
                Kepala {{ $uptName ?? ($user->upt ?? '-') }}<br>
                
                <br><br><br><br>
                
                <strong>{{ $penandatangan->kepala ?? 'Nama Lengkap' }}</strong><br>
                NIP. {{ $penandatangan->nip_kapus ?? '_______________' }}
            </td>
        </tr>
    </table>
</div>
@endif

</body>
</html>