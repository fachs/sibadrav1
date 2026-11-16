@extends('layouts.vertical', ['title' => 'Ringkasan RBA','subTitle' => 'RBA'])
@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
@endsection
@section('content')
@include('partials.rba-tahun-filter', ['tahunRba' => $tahun])
@php
    if (!function_exists('aggregateDataByPrefix')) {
        function aggregateDataByPrefix($groupedData, $isBelanja = false, $isPerubahan = false) {
            $aggregated = [];
            
            foreach($groupedData as $kodeGroup => $items) {
                foreach($items as $item) {
                    if ($isBelanja) {
                        $kodeFull = optional($item->akuns)->kode_rek ?? '';
                        $jumlah = $isPerubahan ? ((float) ($item->proposed_jumlah_harga ?? $item->jumlah_harga)) : ((float) $item->jumlah_harga);
                    } else {
                        $kodeFull = $item->kode_rek;
                        $jumlah = $isPerubahan ? ((float) ($item->jumlah_perubahan ?? $item->jumlah)) : ((float) $item->jumlah);
                    }
                    
                    if (empty($kodeFull)) continue;
                    
                    $parts = explode('.', $kodeFull);
                    $prefix2 = implode('.', array_slice($parts, 0, 2));
                    $prefix3 = implode('.', array_slice($parts, 0, 3));
                    
                    if (!isset($aggregated[$prefix2])) {
                        $label2 = 'AKUMULASI ' . $prefix2;
                        if ($prefix2 == '5.1') $label2 = 'BELANJA OPERASI';
                        elseif ($prefix2 == '5.2') $label2 = 'BELANJA MODAL';
                        elseif ($prefix2 == '5.3') $label2 = 'BELANJA TAK TERDUGA';
                        elseif ($prefix2 == '5.4') $label2 = 'BELANJA TRANSFER';
                        elseif ($prefix2 == '4.1') $label2 = 'JASA LAYANAN';
                        elseif ($prefix2 == '4.2') $label2 = 'HIBAH';
                        elseif ($prefix2 == '4.3') $label2 = 'HASIL KERJASAMA';
                        elseif ($prefix2 == '4.4') $label2 = 'APBD';
                        elseif ($prefix2 == '4.5') $label2 = 'Lain-lain Pendapatan BLUD yang Sah';
                        elseif ($prefix2 == '6.1') $label2 = 'PENERIMAAN PEMBIAYAAN';
                        elseif ($prefix2 == '6.2') $label2 = 'PENGELUARAN PEMBIAYAAN';
                        
                        $aggregated[$prefix2] = [
                            'kode_rek' => $prefix2,
                            'uraian' => $label2,
                            'jumlah' => 0,
                            'children' => []
                        ];
                    }
                    
                    if (!isset($aggregated[$prefix2]['children'][$prefix3])) {
                        $label3 = 'Akumulasi ' . $prefix3;
                        if ($prefix3 == '5.1.01') $label3 = 'Belanja Pegawai BLUD';
                        elseif ($prefix3 == '5.1.02') $label3 = 'Belanja Barang dan Jasa BLUD';
                        elseif ($prefix3 == '5.2.02') $label3 = 'Belanja Modal Peralatan dan Mesin BLUD';
                        elseif ($prefix3 == '5.2.03') $label3 = 'Belanja Modal Gedung dan Bangunan BLUD';
                        elseif ($prefix3 == '5.2.04') $label3 = 'Belanja Modal Jalan, Jaringan dan Irigasi BLUD';
                        
                        if (str_starts_with($prefix3, '4.')) {
                            if ($prefix2 == '4.1') $label3 = 'Jasa Layanan BLUD';
                            elseif ($prefix2 == '4.2') $label3 = 'Hibah BLUD';
                            elseif ($prefix2 == '4.3') $label3 = 'Hasil Kerjasama BLUD';
                            elseif ($prefix2 == '4.4') $label3 = 'APBD BLUD';
                            elseif ($prefix2 == '4.5') $label3 = 'Lain-lain Pendapatan BLUD yang Sah';
                        } elseif (str_starts_with($prefix3, '6.')) {
                            if ($prefix2 == '6.1') $label3 = 'Penerimaan Pembiayaan BLUD';
                            elseif ($prefix2 == '6.2') $label3 = 'Pengeluaran Pembiayaan BLUD';
                        }
                        
                        $aggregated[$prefix2]['children'][$prefix3] = [
                            'kode_rek' => $prefix3,
                            'uraian' => $label3,
                            'jumlah' => 0
                        ];
                    }
                    
                    $aggregated[$prefix2]['jumlah'] += $jumlah;
                    $aggregated[$prefix2]['children'][$prefix3]['jumlah'] += $jumlah;
                }
            }
            
            ksort($aggregated);
            foreach($aggregated as &$group) {
                ksort($group['children']);
            }
            return $aggregated;
        }
    }
    
    $surplusDefisit = $pendapatan['grandTotalJumlah'] - $belanja['grandTotalJumlah'];
    
    $pembiayaanAgg = aggregateDataByPrefix($pembiayaan['grouped'], false, false);
    $penerimaanPembiayaan = $pembiayaanAgg['6.1']['jumlah'] ?? 0;
    $pengeluaranPembiayaan = $pembiayaanAgg['6.2']['jumlah'] ?? 0;
    $pembiayaanNetto = $penerimaanPembiayaan - $pengeluaranPembiayaan;
    
    $silpa = $surplusDefisit + $pembiayaanNetto;
@endphp

@php
    $ringkasanMetrics = [
        ['label' => 'SURPLUS / DEFISIT', 'value' => $surplusDefisit, 'icon' => 'ri-scales-line', 'tone' => 'primary'],
        ['label' => 'PEMBIAYAAN NETTO', 'value' => $pembiayaanNetto, 'icon' => 'ri-exchange-funds-line', 'tone' => 'success'],
        ['label' => 'SISA LEBIH PEMBIAYAAN ANGGARAN TAHUN BERKENAAN (SILPA)', 'value' => $silpa, 'icon' => 'ri-calculator-line', 'tone' => 'warning'],
    ];
    $ringkasanSections = [
        ['kode' => '4', 'label' => 'PENDAPATAN', 'total' => $pendapatan['grandTotalJumlah'], 'groups' => aggregateDataByPrefix($pendapatan['grouped'], false, false), 'tone' => 'primary'],
        ['kode' => '5', 'label' => 'BELANJA', 'total' => $belanja['grandTotalJumlah'], 'groups' => aggregateDataByPrefix($belanja['grouped'], true, false), 'tone' => 'info'],
        ['kode' => '6', 'label' => 'PEMBIAYAAN', 'total' => $pembiayaan['grandTotalJumlah'], 'groups' => $pembiayaanAgg, 'tone' => 'success'],
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach($ringkasanMetrics as $metric)
        <div class="col-12 col-md-4">
            <div class="card rba-budget h-100 mb-0">
                <div class="card-body rba-metric">
                    <div class="rba-metric-top">
                        <span class="rba-heading-icon rba-metric-icon--{{ $metric['tone'] }}"><i class="{{ $metric['icon'] }}" aria-hidden="true"></i></span>
                        <span class="rba-count">{{ $tahun }}</span>
                    </div>
                    <p class="rba-metric-label">{{ $metric['label'] }}</p>
                    <h4 class="rba-metric-value {{ $metric['value'] < 0 ? 'rba-metric-negative' : '' }}">Rp {{ number_format($metric['value'], 2, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card rba-budget">
    
    <div class="card-body">
        <div class="rba-toolbar">
            @if(Auth::user()->hasRole('Superuser'))
                <form action="{{ route('rba.ringkasan') }}" method="GET" class="rba-filter rba-filter--summary">
                    <input type="hidden" name="tahun" value="{{ $tahun }}">
                    <label for="puskesmas" class="form-label mb-0">Unit BLUD</label>
                    <select name="puskesmas" id="puskesmas" class="form-select">
                        <option value="">Semua Unit BLUD</option>
                        @foreach($puskesmasList as $pusk)
                            <option value="{{ $pusk->nama }}" @selected(request('puskesmas') === $pusk->nama)>{{ $pusk->nama }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if(request()->filled('puskesmas'))
                        <a href="{{ route('rba.ringkasan', ['tahun' => $tahun]) }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </form>
            @endif
            <div class="rba-toolbar-actions">
                <a href="{{ route('rba.ringkasan.pdf', ['tahun' => $tahun, 'puskesmas' => request('puskesmas')]) }}" class="btn btn-success"><i class="ri-file-pdf-2-line" aria-hidden="true"></i> Unduh PDF</a>
            </div>
        </div>
        <div class="table-responsive rba-table-wrap" role="region" aria-labelledby="ringkasan-table-title" tabindex="0">
            <table class="table align-middle rba-table rba-table--summary">
                <caption class="visually-hidden">Ringkasan RBA tahun anggaran {{ $tahun }}, berdasarkan klasifikasi rekening.</caption>
                <colgroup><col class="rba-col-code"><col><col class="rba-col-amount"></colgroup>
                <thead><tr><th scope="col">Kode Rekening</th><th scope="col">Uraian</th><th scope="col" class="text-end">Jumlah (Rp)</th></tr></thead>
                <tbody>
                    @if($pendapatan['grouped']->isEmpty() && $belanja['grouped']->isEmpty() && $pembiayaan['grouped']->isEmpty())
                        <tr><td colspan="3" class="rba-empty"><i class="ri-file-list-3-line" aria-hidden="true"></i><p class="fw-semibold text-body-emphasis mb-1">Belum ada data ringkasan RBA.</p><p class="small mb-0">Ringkasan akan tampil setelah rencana anggaran ditambahkan.</p></td></tr>
                    @endif
                    @foreach($ringkasanSections as $section)
                        <tr class="rba-group rba-group--{{ $section['tone'] }}">
                            <th scope="row">{{ $section['kode'] }}</th>
                            <td><div class="d-flex align-items-center gap-2"><i class="ri-folder-2-line" aria-hidden="true"></i>{{ $section['label'] }}</div></td>
                            <td class="text-end rba-amount">{{ number_format($section['total'], 2, ',', '.') }}</td>
                        </tr>
                        @foreach($section['groups'] as $group)
                            <tr class="rba-section">
                                <th scope="row">{{ $group['kode_rek'] }}</th>
                                <td colspan="2">{{ $group['uraian'] }}</td>
                            </tr>
                            @foreach($group['children'] as $data)
                                <tr class="rba-item">
                                    <td><span class="rba-summary-code">{{ $data['kode_rek'] }}</span></td>
                                    <td class="rba-summary-description">{{ $data['uraian'] }}</td>
                                    <td class="text-end rba-amount">{{ number_format($data['jumlah'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="rba-subtotal"><th scope="row" colspan="2" class="text-end">TOTAL {{ $group['uraian'] }}</th><td class="text-end rba-amount">{{ number_format($group['jumlah'], 2, ',', '.') }}</td></tr>
                        @endforeach
                        @if($section['kode'] === '5')
                            <tr class="rba-subtotal"><th scope="row" colspan="2" class="text-end">Total Belanja</th><td class="text-end rba-amount">{{ number_format($belanja['grandTotalJumlah'], 2, ',', '.') }}</td></tr>
                            <tr class="rba-summary-balance {{ $surplusDefisit < 0 ? 'rba-summary-balance--negative' : '' }}"><th scope="row" colspan="2" class="text-end">SURPLUS/ DEFISIT</th><td class="text-end rba-amount">{{ number_format($surplusDefisit, 2, ',', '.') }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th scope="row" colspan="2">Pembiayaan Neto</th><td class="text-end rba-amount">{{ number_format($pembiayaanNetto, 2, ',', '.') }}</td></tr>
                    <tr class="rba-summary-grand-total"><th scope="row" colspan="2">Sisa Lebih Pembiayaan Anggaran Tahun Berkenaan (SiLPA)</th><td class="text-end rba-amount">{{ number_format($silpa, 2, ',', '.') }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
