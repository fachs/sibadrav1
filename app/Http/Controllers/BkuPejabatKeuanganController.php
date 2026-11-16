<?php

namespace App\Http\Controllers;

use App\Models\BkuPejabatKeuangan;
use App\Models\Akun;
use App\Models\UnitBlud;
use App\Support\BkuPejabatKeuanganData;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;

class BkuPejabatKeuanganController extends Controller
{
    use \App\Support\UnitBludPengguna;
    use \App\Support\BkuPuskesmasSelection;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->check()) {
                $dinas = $this->isDinasBkuViewer();
                $viewKepalaAtauDinas = ($dinas || auth()->user()->hasRole('Kepala Puskesmas'))
                    && $request->routeIs('bku-pejabat-keuangan.index', 'bku-pejabat-keuangan.pdf');
                abort_unless($viewKepalaAtauDinas || (!$dinas && auth()->user()->hasRole('Pejabat Keuangan')), 403);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $selectedBulan = $request->get('bulan');
        $isDinasViewer = $this->isDinasBkuViewer();
        $isReadOnlyViewer = $isDinasViewer || !auth()->user()->hasRole('Pejabat Keuangan');
        $selectedUnitBlud = $this->selectedBkuPuskesmas($request);
        $puskesmasList = $isDinasViewer ? $this->puskesmasOptions() : collect();
        $pendingApprovalCount = $this->pendingKepalaBkuCount($selectedUnitBlud);

        $rbaQuery = \App\Models\RencanaKebutuhan::query()
            ->with('akuns')
            ->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
            ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) auth()->user()->upt))])
            ->whereNotNull('akun_id');
        $rbas = $rbaQuery->get();
        $paguRekening = $rbas->filter(fn ($rba) => $rba->akuns?->kode_rek)
            ->groupBy(fn ($rba) => $rba->akuns->kode_rek)
            ->map(fn ($items) => (int) $items->sum('active_jumlah_harga'));
        $terpakaiRekening = $this->bkuQuery()
            ->where('is_saldo_awal', false)
            ->selectRaw('kode_rekening, SUM(pengeluaran) AS total')
            ->groupBy('kode_rekening')
            ->pluck('total', 'kode_rekening');
        $sisaPaguRekening = $paguRekening->map(fn ($pagu, $kode) => max(0, (int) $pagu - (int) $terpakaiRekening->get($kode, 0)));

        $data = app(BkuPejabatKeuanganData::class)->report($selectedUnitBlud?->id, $selectedBulan, \App\Support\TahunAnggaran::selected());
        $bulanList = BkuPejabatKeuanganData::BULAN;

        return view('bku-pejabat-keuangan', $data + compact('selectedBulan', 'bulanList', 'isDinasViewer', 'isReadOnlyViewer', 'selectedUnitBlud', 'puskesmasList', 'pendingApprovalCount', 'rbas', 'paguRekening', 'sisaPaguRekening'));
    }

    public function pdf(Request $request)
    {
        $selectedBulan = $request->get('bulan');
        $user = auth()->user();
        $unitBlud = $this->selectedBkuPuskesmas($request);
        abort_if($this->isDinasBkuViewer() && !$unitBlud, 422, 'Pilih puskesmas terlebih dahulu.');

        $report = app(BkuPejabatKeuanganData::class)->report($unitBlud?->id, $selectedBulan, \App\Support\TahunAnggaran::selected());

        $namaPuskesmas = $unitBlud?->nama ?? $user->upt;
        $namaKapus = $unitBlud?->kepala ?? '........................';
        $nipKapus = $unitBlud?->nip_kapus ?? '........................';
        $namaKtu = $unitBlud?->kasubbag_tu ?? '........................';
        $nipKtu = $unitBlud?->nip_ktu ?? '........................';

        $tahun = \App\Support\TahunAnggaran::selected();

        $data = $report + [
            'tahun' => $tahun,
            'selectedBulan' => $selectedBulan ?: 'Semua Periode',
            'jumlahSaatIni' => $report['totalPenerimaan'],
            'namaPuskesmas' => $namaPuskesmas,
            'namaKapus' => $namaKapus,
            'nipKapus' => $nipKapus,
            'namaKtu' => $namaKtu,
            'nipKtu' => $nipKtu,
        ];

        $pdf = Pdf::loadView('pdf.bku-pejabat-keuangan', $data)->setPaper('a4', 'landscape');
        
        $filename = 'BKU-Pejabat-Keuangan-' . ($selectedBulan ?: 'Semua') . '-' . $tahun . '.pdf';
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
            'pajaks.*.jenis' => ['required_with:pajaks', Rule::in(\App\Models\BkuBendaharaPengeluaran::JENIS_PAJAK)],
            'pajaks.*.nominal' => ['required_with:pajaks', 'regex:/^[0-9]+(?:\.[0-9]{3})*$/'],
            'penerimaan' => 'prohibited',
            'pengeluaran' => 'prohibited',
            'giro' => 'prohibited',
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

        $pagu = (int) \App\Models\RencanaKebutuhan::query()
            ->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
            ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) auth()->user()->upt))])
            ->whereHas('akuns', fn ($query) => $query->where('kode_rek', $validated['kode_rekening']))
            ->get()->sum('active_jumlah_harga');
        $terpakai = (int) $this->bkuQuery()->where('is_saldo_awal', false)
            ->where('kode_rekening', $validated['kode_rekening'])->sum('pengeluaran');
        $sisaPagu = max(0, $pagu - $terpakai);
        if ($pengeluaran > $sisaPagu) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'jumlah' => 'Jumlah melebihi sisa pagu rekening. Sisa yang dapat digunakan: Rp '.number_format($sisaPagu, 0, ',', '.').'.',
            ]);
        }

        // Hitung Saldo (Saldo sebelumnya + penerimaan - pengeluaran)
        $lastBku = $this->bkuQuery()->orderBy('tanggal', 'desc')->orderBy('id', 'desc')->first();
        $saldoSebelumnya = $lastBku ? $lastBku->saldo : 0;
        $saldo = $saldoSebelumnya - $pengeluaran;

        BkuPejabatKeuangan::create([
            'unit_blud_id' => $unitId,
            'no_transaksi' => $validated['no_transaksi'] ?? null,
            'tanggal' => $validated['tanggal'],
            'kode_rekening' => $validated['kode_rekening'],
            'subkegiatan' => $validated['subkegiatan'],
            'uraian' => $validated['uraian'],
            'penerimaan' => 0,
            'pengeluaran' => $pengeluaran,
            'saldo' => $saldo,
            'giro' => 0,
            'jenis_pajak' => $pajakItems[0]['jenis'] ?? null,
            'nominal_pajak' => $nominalPajak,
            'rincian_pajak' => $pajakItems,
        ]);

        $this->recalculateSaldo();

        return redirect()->route('bku-pejabat-keuangan.index')->with('success', 'Data BKU Pejabat Keuangan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $bku = $this->bkuQuery()->findOrFail($id);

        $validated = $request->validate([
            'no_transaksi' => 'nullable|string|max:255',
            'tanggal' => \App\Support\TahunAnggaran::dateRules(),
            'kode_rekening' => 'nullable|string|max:255',
            'uraian' => 'required|string',
            'penerimaan' => 'nullable|string',
            'pengeluaran' => 'nullable|string',
            'giro' => 'nullable|string'
        ]);

        $penerimaan = isset($validated['penerimaan']) ? (int) str_replace('.', '', $validated['penerimaan']) : 0;
        $pengeluaran = isset($validated['pengeluaran']) ? (int) str_replace('.', '', $validated['pengeluaran']) : 0;
        $giro = isset($validated['giro']) ? (int) str_replace('.', '', $validated['giro']) : 0;

        $bku->update([
            'no_transaksi' => $validated['no_transaksi'] ?? null,
            'tanggal' => $validated['tanggal'],
            'kode_rekening' => $validated['kode_rekening'] ?? null,
            'uraian' => $validated['uraian'],
            'penerimaan' => $penerimaan,
            'pengeluaran' => $pengeluaran,
            'giro' => $giro,
        ]);

        $this->recalculateSaldo();

        return redirect()->route('bku-pejabat-keuangan.index')->with('success', 'Data BKU Pejabat Keuangan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $bku = $this->bkuQuery()->findOrFail($id);
        $bku->delete();

        $this->recalculateSaldo();

        return redirect()->route('bku-pejabat-keuangan.index')->with('success', 'Data BKU Pejabat Keuangan berhasil dihapus.');
    }

    public function setSaldoAwal(Request $request)
    {
        $unitId = $this->wajibUnitBludPengguna();
        $validated = $request->validate([
            'tanggal' => \App\Support\TahunAnggaran::dateRules(),
            'saldo_awal' => 'required|string',
        ]);

        $saldoAwalAmount = (int) str_replace('.', '', $validated['saldo_awal']);

        $bkuSaldoAwal = $this->bkuQuery()->where('is_saldo_awal', true)
            ->whereYear('tanggal', substr($validated['tanggal'], 0, 4))->first();

        if ($bkuSaldoAwal) {
            $bkuSaldoAwal->update([
                'tanggal' => $validated['tanggal'],
                'saldo' => $saldoAwalAmount,
                'penerimaan' => 0,
            ]);
        } else {
            BkuPejabatKeuangan::create([
                'unit_blud_id' => $unitId,
                'no_transaksi' => '-',
                'tanggal' => $validated['tanggal'],
                'kode_rekening' => '-',
                'uraian' => 'Saldo Awal',
                'penerimaan' => 0,
                'pengeluaran' => 0,
                'saldo' => $saldoAwalAmount,
                'giro' => 0,
                'is_saldo_awal' => true,
            ]);
        }

        $this->recalculateSaldo();

        return redirect()->route('bku-pejabat-keuangan.index')->with('success', 'Saldo Awal berhasil diatur.');
    }

    private function bkuQuery()
    {
        return \App\Support\TahunAnggaran::dates($this->batasiUnitBlud(BkuPejabatKeuangan::query()));
    }

    private function recalculateSaldo()
    {
        $report = app(BkuPejabatKeuanganData::class)->report($this->unitBludPengguna(), null, \App\Support\TahunAnggaran::selected());
        foreach ($report['bkuList'] as $bku) {
            if (!$bku->is_saldo_awal && !$bku->is_penyetoran && !$bku->is_spd) {
                $this->bkuQuery()->whereKey($bku->id)->update(['saldo' => $bku->saldo]);
            }
        }
    }
}
