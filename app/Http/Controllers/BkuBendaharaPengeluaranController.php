<?php

namespace App\Http\Controllers;

use App\Models\BkuBendaharaPengeluaran;
use App\Models\Akun;
use App\Models\BkuBendaharaPerubahanRequest;
use App\Models\SpdGu;
use App\Models\SpdLs;
use App\Models\Up;
use App\Models\UnitBlud;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BkuBendaharaPengeluaranController extends Controller
{
    use \App\Support\UnitBludPengguna;
    use \App\Support\BkuPuskesmasSelection;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->check()) {
                $dinas = $this->isDinasBkuViewer();
                $viewKepalaAtauDinas = ($dinas || auth()->user()->hasRole('Kepala Puskesmas'))
                    && $request->routeIs('bku-bendahara-pengeluaran.index', 'bku-bendahara-pengeluaran.pdf');
                abort_unless($viewKepalaAtauDinas || (!$dinas && auth()->user()->hasRole('Bendahara Pengeluaran')), 403);
            }
            return $next($request);
        });
    }

    private function getCombinedBkuData(?int $unitId)
    {
        // Data manual
        $manualBku = \App\Support\TahunAnggaran::dates(BkuBendaharaPengeluaran::query())
            ->where('unit_blud_id', $unitId ?? 0)->where('is_saldo_awal', false)->get()->map(function($item) {
            $item->is_manual = $item->parent_bku_id === null;
            $item->type = $item->parent_bku_id === null ? 'manual' : 'pajak';
            return $item;
        });

        // Data dari GU (SpdGu)
        $spdGuList = SpdGu::with('opdGu.ppdGu')->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
            ->where('unit_blud_id', $unitId ?? 0)->get()->map(function($gu) {
            $obj = new \stdClass();
            $obj->unit_blud_id = $gu->unit_blud_id;
            $obj->id = 'gu_'.$gu->id;
            $obj->no_transaksi = $gu->nomor;
            $obj->tanggal = $gu->tanggal_dba ?? $gu->created_at->format('Y-m-d');
            $obj->kode_rekening = '-';
            $obj->uraian = 'Penerimaan GU';
            $obj->penerimaan = $gu->jumlah_akhir; 
            $obj->pengeluaran = 0;
            $obj->saldo = 0;
            $obj->giro = 0;
            $obj->is_saldo_awal = false;
            $obj->is_manual = false;
            $obj->type = 'gu';
            return $obj;
        });

        // Data dari LS (SpdLs)
        $spdLsList = SpdLs::with('opdLs.ppdLs')->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
            ->where('unit_blud_id', $unitId ?? 0)->get()->map(function($ls) {
            $obj = new \stdClass();
            $obj->unit_blud_id = $ls->unit_blud_id;
            $obj->id = 'ls_'.$ls->id;
            $obj->real_id = $ls->id;
            $obj->no_transaksi = $ls->nomor;
            $obj->tanggal = $ls->tanggal_dba ?? $ls->created_at->format('Y-m-d');
            $obj->kode_rekening = $ls->opdLs->ppdLs->kode_rekening ?? '-';
            $obj->uraian = $ls->opdLs->ppdLs->uraian ?? ('Pencairan LS - ' . $ls->kepada);
            $obj->penerimaan = $ls->jumlah_akhir; 
            $obj->pengeluaran = 0;
            $obj->saldo = 0;
            $obj->giro = 0;
            $obj->is_saldo_awal = false;
            $obj->is_manual = false;
            $obj->type = 'ls';
            return $obj;
        });

        $spdUps = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
            ->where('unit_blud_id', $unitId ?? 0)
            ->whereNotNull('no_spd')->where('no_spd', '<>', '')->orderBy('id')->get();
        $saldoAwal = collect();
        if ($spdUps->isNotEmpty()) {
            $saldoAwal->push((object) [
                'id' => 'up_saldo_awal',
                'no_transaksi' => $spdUps->pluck('no_spd')->implode(', '),
                'tanggal' => \App\Support\TahunAnggaran::selected().'-01-01',
                'kode_rekening' => '-',
                'uraian' => 'Saldo Awal (SPD UP)',
                'penerimaan' => 0,
                'pengeluaran' => 0,
                'saldo' => $spdUps->sum(fn ($up) => max(0, (int) $up->jumlah - (int) $up->potongan)),
                'giro' => 0,
                'is_saldo_awal' => true,
                'is_manual' => false,
                'type' => 'up',
                'unit_blud_id' => $unitId,
            ]);
        }

        $allBku = collect()->concat($saldoAwal)->concat($manualBku)->concat($spdGuList)->concat($spdLsList);
        
        $spdLsMap = collect($spdLsList)->keyBy('real_id');
        
        $allBku = $allBku->sortBy(function($item) use ($spdLsMap) {
            if ($item->type === 'pajak') {
                return Carbon::parse($item->tanggal)->timestamp.'_zzz_'
                    .str_pad($item->parent_bku_id, 10, '0', STR_PAD_LEFT).'_'
                    .str_pad($item->pajak_urutan, 3, '0', STR_PAD_LEFT);
            }
            if ($item->is_manual && isset($item->spd_ls_id) && $item->spd_ls_id) {
                $parent = $spdLsMap->get($item->spd_ls_id);
                $timestamp = $parent ? Carbon::parse($parent->tanggal)->timestamp : Carbon::parse($item->tanggal)->timestamp;
                return $timestamp . '_ls_' . str_pad($item->spd_ls_id, 10, '0', STR_PAD_LEFT) . '_1_child_' . $item->id;
            }
            $timestamp = Carbon::parse($item->tanggal)->timestamp;
            if ($item->type === 'ls') {
                return $timestamp . '_ls_' . str_pad($item->real_id, 10, '0', STR_PAD_LEFT) . '_0_parent';
            }
            if ($item->is_manual) {
                return $timestamp.'_zzz_'.str_pad($item->id, 10, '0', STR_PAD_LEFT).'_000';
            }
            return $timestamp . '_zzz_' . $item->id;
        })->values();
        
        $currentSaldo = 0;
        foreach ($allBku as $bku) {
            if ($bku->is_saldo_awal) {
                $currentSaldo = $bku->saldo;
            } else {
                $currentSaldo += $bku->penerimaan - $bku->pengeluaran;
                $bku->saldo = $currentSaldo;
            }
        }
        
        return $allBku;
    }

    public function index(Request $request)
    {
        $selectedBulan = $request->get('bulan');
        $isDinasViewer = $this->isDinasBkuViewer();
        $isReadOnlyViewer = $isDinasViewer || !auth()->user()->hasRole('Bendahara Pengeluaran');
        $selectedUnitBlud = $this->selectedBkuPuskesmas($request);
        $puskesmasList = $isDinasViewer ? $this->puskesmasOptions() : collect();
        $pendingApprovalCount = $this->pendingKepalaBkuCount($selectedUnitBlud);
        $allBku = $this->getCombinedBkuData($selectedUnitBlud?->id);

        if ($selectedBulan) {
            $bulanMapping = [
                'Januari' => '01', 'Februari' => '02', 'Maret' => '03',
                'April' => '04', 'Mei' => '05', 'Juni' => '06',
                'Juli' => '07', 'Agustus' => '08', 'September' => '09',
                'Oktober' => '10', 'November' => '11', 'Desember' => '12',
            ];
            
            if (isset($bulanMapping[$selectedBulan])) {
                $month = $bulanMapping[$selectedBulan];
                $bkuList = $allBku->filter(function($item) use ($month) {
                    return Carbon::parse($item->tanggal)->format('m') == $month || $item->is_saldo_awal;
                })->values();
            } else {
                $bkuList = $allBku;
            }

        } else {
            $bkuList = $allBku;
        }

        $manualRows = $bkuList->filter(fn ($row) => $row->is_manual)->keyBy('id');
        $changeRequests = BkuBendaharaPerubahanRequest::where('unit_blud_id', $selectedUnitBlud?->id ?? 0)
            ->whereIn('bku_id', $bkuList->filter(fn ($row) => $row->is_manual)->pluck('id'))
            ->latest('id')->get()
            ->filter(fn ($item) => $item->status === 'pending'
                || ($item->status === 'approved' && $manualRows->has($item->bku_id)
                    && $item->verifikator === BkuBendaharaPerubahanRequest::verifikatorUntukTanggal($manualRows->get($item->bku_id)->tanggal)))
            ->unique(fn ($item) => $item->bku_id.':'.$item->aksi)
            ->keyBy(fn ($item) => $item->bku_id.':'.$item->aksi);
        $totalPenerimaan = $bkuList->where('is_saldo_awal', false)->sum('penerimaan');
        $totalPengeluaran = $bkuList->where('is_saldo_awal', false)->sum('pengeluaran');
        
        $lastBku = $bkuList->last();
        $saldoAkhir = $lastBku ? $lastBku->saldo : 0;

        $bulanList = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        $rbaQuery = \App\Models\RencanaKebutuhan::with('akuns')->whereNotNull('akun_id');
        $rbaQuery->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) ($selectedUnitBlud?->nama ?? auth()->user()->upt)))]);
        $rbasRaw = $rbaQuery->get();
        $paguRekening = $rbasRaw->filter(fn ($item) => $item->akuns?->kode_rek)
            ->groupBy(fn ($item) => $item->akuns->kode_rek)
            ->map(fn ($items) => (int) $items->sum('active_jumlah_harga'));
        $realisasiRekening = \App\Support\TahunAnggaran::dates(BkuBendaharaPengeluaran::query())
            ->where('unit_blud_id', $selectedUnitBlud?->id ?? 0)
            ->whereNull('parent_bku_id')->where('is_saldo_awal', false)
            ->selectRaw('kode_rekening, SUM(pengeluaran) AS total')
            ->groupBy('kode_rekening')->pluck('total', 'kode_rekening');
        $sisaPaguRekening = $paguRekening->map(fn ($pagu, $kode) => max(0, $pagu - (int) ($realisasiRekening->get($kode) ?? 0)));
        $rbas = $rbasRaw->groupBy(fn ($item) => $item->subkegiatan.'|'.$item->akun_id)->map(function ($items) {
            $first = $items->first();
            $first->total_anggaran = $items->sum('active_jumlah_harga');
            return $first;
        })->filter(function($item) {
            return $item->akuns && $item->akuns->kode_rek;
        })->values();

        $uraianRekening = Akun::whereIn('kode_rek', $bkuList->pluck('kode_rekening')->filter()->unique())
            ->pluck('uraian', 'kode_rek');

        return view('bku-bendahara-pengeluaran', compact('bkuList', 'changeRequests', 'totalPenerimaan', 'totalPengeluaran', 'saldoAkhir', 'selectedBulan', 'bulanList', 'rbas', 'uraianRekening', 'isDinasViewer', 'isReadOnlyViewer', 'selectedUnitBlud', 'puskesmasList', 'pendingApprovalCount', 'paguRekening', 'sisaPaguRekening'));
    }

    public function pdf(Request $request)
    {
        $selectedBulan = $request->get('bulan');
        $user = auth()->user();
        $unitBlud = $this->selectedBkuPuskesmas($request);
        abort_if($this->isDinasBkuViewer() && !$unitBlud, 422, 'Pilih puskesmas terlebih dahulu.');
        $allBku = $this->getCombinedBkuData($unitBlud?->id);

        $bulanMapping = [
            'Januari' => '01', 'Februari' => '02', 'Maret' => '03',
            'April' => '04', 'Mei' => '05', 'Juni' => '06',
            'Juli' => '07', 'Agustus' => '08', 'September' => '09',
            'Oktober' => '10', 'November' => '11', 'Desember' => '12',
        ];

        if ($selectedBulan && isset($bulanMapping[$selectedBulan])) {
            $month = $bulanMapping[$selectedBulan];
            $bkuList = $allBku->filter(function($item) use ($month) {
                return Carbon::parse($item->tanggal)->format('m') == $month || $item->is_saldo_awal;
            })->values();
            
            $jumlahSdTanggalLalu = $allBku->filter(function($item) use ($month) {
                return Carbon::parse($item->tanggal)->format('m') < $month && !$item->is_saldo_awal;
            })->sum('penerimaan');
        } else {
            $bkuList = $allBku;
            $jumlahSdTanggalLalu = 0;
        }

        $totalPenerimaan = $bkuList->where('is_saldo_awal', false)->sum('penerimaan');
        $totalPengeluaran = $bkuList->where('is_saldo_awal', false)->sum('pengeluaran');
        $totalGiro = $bkuList->sum('giro');
        
        $lastBku = $bkuList->last();
        $saldoAkhir = $lastBku ? $lastBku->saldo : 0;

        $jumlahSaatIni = $totalPenerimaan;

        $namaPuskesmas = $unitBlud?->nama ?? $user->upt;
        $namaKapus = $unitBlud?->kepala ?? '........................';
        $nipKapus = $unitBlud?->nip_kapus ?? '........................';
        $namaKtu = $unitBlud?->kasubbag_tu ?? '........................';
        $nipKtu = $unitBlud?->nip_ktu ?? '........................';

        $tahun = \App\Support\TahunAnggaran::selected();

        $data = [
            'tahun' => $tahun,
            'selectedBulan' => $selectedBulan ?: 'Semua Periode',
            'bkuList' => $bkuList,
            'totalPenerimaan' => $totalPenerimaan,
            'totalPengeluaran' => $totalPengeluaran,
            'totalGiro' => $totalGiro,
            'saldoAkhir' => $saldoAkhir,
            'jumlahSaatIni' => $jumlahSaatIni,
            'jumlahSdTanggalLalu' => $jumlahSdTanggalLalu,
            'namaPuskesmas' => $namaPuskesmas,
            'namaKapus' => $namaKapus,
            'nipKapus' => $nipKapus,
            'namaKtu' => $namaKtu,
            'nipKtu' => $nipKtu,
        ];

        $pdf = Pdf::loadView('pdf.bku-bendahara-pengeluaran', $data)->setPaper('a4', 'landscape');
        
        $filename = 'bku-bendahara-pengeluaran-' . ($selectedBulan ?: 'Semua') . '-' . $tahun . '.pdf';
        return $pdf->download($filename);
    }

    public function store(Request $request)
    {
        $unitId = $this->wajibUnitBludPengguna();
        $rbaQuery = \App\Models\RencanaKebutuhan::query()
            ->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
            ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) auth()->user()->upt))])
            ->whereNotNull('akun_id');
        $subkegiatanList = (clone $rbaQuery)->whereNotNull('subkegiatan')->distinct()->pluck('subkegiatan');
        $akunIds = (clone $rbaQuery)->where('subkegiatan', $request->input('subkegiatan'))->pluck('akun_id');
        $validated = $request->validate([
            'no_transaksi' => 'nullable|string|max:255',
            'tanggal' => \App\Support\TahunAnggaran::dateRules(),
            'kode_rekening' => ['required', Rule::exists('akuns', 'kode_rek')->whereIn('id', $akunIds->all())],
            'subkegiatan' => ['required', Rule::in($subkegiatanList->all())],
            'uraian' => 'required|string',
            'jumlah' => ['required', 'regex:/^[0-9]+(?:\.[0-9]{3})*$/'],
            'pajaks' => 'nullable|array',
            'pajaks.*.jenis' => ['required_with:pajaks', Rule::in(BkuBendaharaPengeluaran::JENIS_PAJAK)],
            'pajaks.*.nominal' => ['required_with:pajaks', 'regex:/^[0-9]+(?:\.[0-9]{3})*$/'],
            'penerimaan' => 'prohibited',
            'pengeluaran' => 'prohibited',
            'giro' => 'prohibited',
            'spd_ls_id' => 'prohibited',
        ]);

        $pengeluaran = (int) str_replace('.', '', $validated['jumlah']);
        $pajakItems = collect($validated['pajaks'] ?? [])->map(fn ($pajak) => [
            'jenis' => trim((string) ($pajak['jenis'] ?? '')) ?: null,
            'nominal' => (int) str_replace('.', '', $pajak['nominal']),
        ])->values()->all();
        $nominalPajak = array_sum(array_column($pajakItems, 'nominal'));
        if ($pengeluaran <= 0 || $nominalPajak > $pengeluaran || collect($pajakItems)->contains(fn ($pajak) => $pajak['nominal'] <= 0)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'jumlah' => 'Jumlah harus lebih dari nol dan total pajak tidak boleh melebihi jumlah. Setiap pajak harus lebih dari nol.',
            ]);
        }

        DB::transaction(function () use ($unitId, $validated, $pengeluaran, $nominalPajak, $pajakItems) {
            $pagu = \App\Models\RencanaKebutuhan::query()
                ->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
                ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) auth()->user()->upt))])
                ->whereHas('akuns', fn ($query) => $query->where('kode_rek', $validated['kode_rekening']))
                ->lockForUpdate()->get()->sum('active_jumlah_harga');
            $terpakai = \App\Support\TahunAnggaran::dates(BkuBendaharaPengeluaran::query())
                ->where('unit_blud_id', $unitId)->where('kode_rekening', $validated['kode_rekening'])
                ->whereNull('parent_bku_id')->where('is_saldo_awal', false)->sum('pengeluaran');
            $sisaPagu = max(0, (int) $pagu - (int) $terpakai);
            if ($pengeluaran > $sisaPagu) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'jumlah' => 'Jumlah melebihi sisa pagu rekening. Sisa yang dapat digunakan: Rp '.number_format($sisaPagu, 0, ',', '.').'.',
                ]);
            }
            $bku = BkuBendaharaPengeluaran::create([
                'unit_blud_id' => $unitId,
                'no_transaksi' => $validated['no_transaksi'] ?? null,
                'tanggal' => $validated['tanggal'],
                'kode_rekening' => $validated['kode_rekening'] ?? null,
                'subkegiatan' => $validated['subkegiatan'] ?? null,
                'uraian' => $validated['uraian'],
                'penerimaan' => 0,
                'pengeluaran' => $pengeluaran,
                'saldo' => 0,
                'giro' => 0,
                'jenis_pajak' => $pajakItems[0]['jenis'] ?? null,
                'nominal_pajak' => $nominalPajak,
                'rincian_pajak' => $pajakItems,
            ]);
            $this->syncPajakTransactions($bku, $pajakItems);
        });

        return redirect()->route('bku-bendahara-pengeluaran.index')->with('success', 'Data BKU Bendahara Pengeluaran berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $bku = $this->bkuQuery()->where('is_saldo_awal', false)->whereNull('parent_bku_id')->findOrFail($id);
        $formEdit = $request->has('jumlah');
        $rbaQuery = \App\Models\RencanaKebutuhan::query()
            ->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
            ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) auth()->user()->upt))])
            ->whereNotNull('akun_id');
        $subkegiatanList = (clone $rbaQuery)->whereNotNull('subkegiatan')->distinct()->pluck('subkegiatan');
        if ($bku->subkegiatan) {
            $subkegiatanList->push($bku->subkegiatan);
        }
        $akunIds = (clone $rbaQuery)->where('subkegiatan', $request->input('subkegiatan'))->pluck('akun_id');
        $kodeRekeningList = Akun::whereIn('id', $akunIds)->pluck('kode_rek');
        if ($request->input('subkegiatan') === $bku->subkegiatan && $bku->kode_rekening) {
            $kodeRekeningList->push($bku->kode_rekening);
        }

        $validated = $request->validate([
            'no_transaksi' => 'nullable|string|max:255',
            'tanggal' => \App\Support\TahunAnggaran::dateRules(),
            'kode_rekening' => $formEdit ? ['required', Rule::in($kodeRekeningList->all())] : 'nullable|string|max:255',
            'subkegiatan' => $formEdit ? ['required', Rule::in($subkegiatanList->all())] : 'nullable|string|max:255',
            'uraian' => 'required|string',
            'jumlah' => $formEdit ? ['required', 'regex:/^[0-9]+(?:\.[0-9]{3})*$/'] : 'nullable|string',
            'penerimaan' => $formEdit ? 'prohibited' : 'nullable|string',
            'pengeluaran' => $formEdit ? 'prohibited' : 'nullable|string',
            'giro' => $formEdit ? 'prohibited' : 'nullable|string',
            'pajaks_present' => 'sometimes|accepted',
            'pajaks' => 'nullable|array',
            'pajaks.*.jenis' => ['required_with:pajaks', Rule::in(BkuBendaharaPengeluaran::JENIS_PAJAK)],
            'pajaks.*.nominal' => ['required_with:pajaks', 'regex:/^[0-9]+(?:\.[0-9]{3})*$/'],
        ]);

        $penerimaan = !$formEdit && isset($validated['penerimaan']) ? (int) str_replace('.', '', $validated['penerimaan']) : 0;
        $pengeluaran = isset($validated['jumlah']) ? (int) str_replace('.', '', $validated['jumlah'])
            : (isset($validated['pengeluaran']) ? (int) str_replace('.', '', $validated['pengeluaran']) : 0);
        $giro = isset($validated['giro']) ? (int) str_replace('.', '', $validated['giro']) : (int) $bku->giro;
        $pajakItems = $formEdit || isset($validated['pajaks_present'])
            ? collect($validated['pajaks'] ?? [])->map(fn ($pajak) => [
                'jenis' => trim((string) ($pajak['jenis'] ?? '')) ?: null,
                'nominal' => (int) str_replace('.', '', $pajak['nominal']),
            ])->values()->all()
            : $bku->pajak_items;
        $nominalPajak = array_sum(array_column($pajakItems, 'nominal'));
        if (($formEdit && $pengeluaran <= 0) || $nominalPajak > $pengeluaran || collect($pajakItems)->contains(fn ($pajak) => $pajak['nominal'] <= 0)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pajaks' => 'Total pajak tidak boleh melebihi pengeluaran dan setiap pajak harus lebih dari nol.',
            ]);
        }

        DB::transaction(function () use ($bku, $validated, $penerimaan, $pengeluaran, $giro, $nominalPajak, $pajakItems) {
            $bku = $this->bkuQuery()->where('is_saldo_awal', false)->whereNull('parent_bku_id')->lockForUpdate()->findOrFail($bku->id);
            $this->authorizeChange($bku, 'edit');
            $bku->update([
                'no_transaksi' => $validated['no_transaksi'] ?? null,
                'tanggal' => $validated['tanggal'],
                'kode_rekening' => $validated['kode_rekening'] ?? null,
                'subkegiatan' => $validated['subkegiatan'] ?? null,
                'uraian' => $validated['uraian'],
                'penerimaan' => $penerimaan,
                'pengeluaran' => $pengeluaran,
                'giro' => $giro,
                'jenis_pajak' => $pajakItems[0]['jenis'] ?? null,
                'nominal_pajak' => $nominalPajak,
                'rincian_pajak' => $pajakItems,
            ]);
            $this->syncPajakTransactions($bku, $pajakItems);
        });

        return redirect()->route('bku-bendahara-pengeluaran.index')->with('success', 'Data BKU Bendahara Pengeluaran berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $bku = $this->bkuQuery()->where('is_saldo_awal', false)->whereNull('parent_bku_id')->findOrFail($id);
        DB::transaction(function () use ($bku) {
            $bku = $this->bkuQuery()->where('is_saldo_awal', false)->whereNull('parent_bku_id')->lockForUpdate()->findOrFail($bku->id);
            $this->authorizeChange($bku, 'hapus');
            BkuBendaharaPengeluaran::where('parent_bku_id', $bku->id)->delete();
            $bku->delete();
        });

        return redirect()->route('bku-bendahara-pengeluaran.index')->with('success', 'Data BKU Bendahara Pengeluaran berhasil dihapus.');
    }

    private function bkuQuery()
    {
        return \App\Support\TahunAnggaran::dates($this->batasiUnitBlud(BkuBendaharaPengeluaran::query()));
    }

    private function syncPajakTransactions(BkuBendaharaPengeluaran $bku, array $pajaks): void
    {
        BkuBendaharaPengeluaran::where('parent_bku_id', $bku->id)->delete();

        foreach ($pajaks as $index => $pajak) {
            $jenis = $pajak['jenis'] ?: 'Pajak';
            foreach (['pemotongan', 'penyetoran'] as $offset => $type) {
                BkuBendaharaPengeluaran::create([
                    'unit_blud_id' => $bku->unit_blud_id,
                    'parent_bku_id' => $bku->id,
                    'pajak_entry_type' => $type,
                    'pajak_urutan' => $index * 2 + $offset + 1,
                    'tanggal' => $bku->tanggal,
                    'kode_rekening' => null,
                    'subkegiatan' => $bku->subkegiatan,
                    'uraian' => ucfirst($type).' '.$jenis,
                    'penerimaan' => $type === 'penyetoran' ? $pajak['nominal'] : 0,
                    'pengeluaran' => $type === 'pemotongan' ? $pajak['nominal'] : 0,
                    'saldo' => 0,
                    'giro' => 0,
                ]);
            }
        }
    }

    private function authorizeChange(BkuBendaharaPengeluaran $bku, string $aksi): void
    {
        if (Carbon::parse($bku->tanggal)->toDateString() === now('Asia/Jakarta')->toDateString()) {
            return;
        }

        $verifikator = BkuBendaharaPerubahanRequest::verifikatorUntukTanggal($bku->tanggal);
        $approval = BkuBendaharaPerubahanRequest::where('bku_id', $bku->id)
            ->where('unit_blud_id', $bku->unit_blud_id)->where('aksi', $aksi)
            ->where('status', 'approved')->where('verifikator', $verifikator)
            ->latest('id')->lockForUpdate()->first();
        $tujuan = $verifikator === BkuBendaharaPerubahanRequest::KEPALA_PUSKESMAS ? 'Kepala Puskesmas' : 'Dinas Kesehatan';
        abort_unless($approval, 403, 'Perubahan transaksi ini memerlukan persetujuan '.$tujuan.'.');
        $approval->update(['status' => 'used']);
    }
}
