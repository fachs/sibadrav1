<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use App\Models\BuktiDukungPenerimaan;
use App\Models\Penerimaan;
use App\Models\UnitBlud;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PenerimaanController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderIndex($request, 'penerimaan', true);
    }

    public function indexPenerimaan(Request $request)
    {
        return $this->renderIndex($request, 'penerimaan', true);
    }

    public function indexPenyetoran(Request $request)
    {
        return $this->renderIndex($request, 'penyetoran', false);
    }

    private function renderIndex(Request $request, string $viewName, bool $allowTambah)
    {
        $selectedPeriode = $request->get('periode');
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
        $selectedUnitBludId = $isDinkes ? $request->get('unit_blud_id') : null;
        $unitBluds = $isDinkes ? UnitBlud::orderBy('nama')->get() : collect();

        if ($isDinkes && filled($selectedUnitBludId)) {
            $selectedUnitBludId = $unitBluds->firstWhere('id', (int) $selectedUnitBludId)?->id;
        }

        $query = Penerimaan::with(['akuns', 'unitBlud'])->where('tahun_anggaran', \App\Support\TahunAnggaran::selected());

        // Resolusi unit berdasarkan UPT dipakai untuk membatasi akun selain Dinas Kesehatan.
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        $bulanMap = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $bulanToAngka = array_flip($bulanMap);

        $today = Carbon::now();
        $bulanSekarangAngka = (int) $today->month;
        $bulanSebelumnyaAngka = (int) $today->copy()->subMonth()->month;
        $periodeSekarang = $bulanMap[$bulanSekarangAngka];
        $periodeSebelumnya = $bulanMap[$bulanSebelumnyaAngka];

        $canTambahPenerimaan = $allowTambah;
        $alasanBlokir = null;

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('unit_blud_id', $unitBlud->id);
            }

            if ($allowTambah && (int) $today->day === 1 && empty($selectedPeriode)) {
                $canTambahPenerimaan = false;
                $alasanBlokir = 'Pilih periode terlebih dahulu untuk menambah penerimaan pada tanggal 1.';
            }

            if ($allowTambah && $canTambahPenerimaan && !empty($selectedPeriode)) {
                $selectedPeriodeAngka = (int) ($bulanToAngka[$selectedPeriode] ?? $bulanSekarangAngka);
                $sebelumnyaAngka = $selectedPeriodeAngka - 1;
                if ($sebelumnyaAngka < 1) $sebelumnyaAngka = 12;
                $periodeSebelumnyaDipilih = $bulanMap[$sebelumnyaAngka];

                $buktiSebelumnya = BuktiDukungPenerimaan::query()->where('tahun_anggaran', \App\Support\TahunAnggaran::selected() - ($selectedPeriodeAngka === 1 ? 1 : 0))
                    ->where('periode', $periodeSebelumnyaDipilih)
                    ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) $user->upt))])
                    ->latest('id')
                    ->first();

                $linkKosong = !$buktiSebelumnya || empty(trim((string) $buktiSebelumnya->link));

                if ($linkKosong) {
                    $canTambahPenerimaan = false;
                    $alasanBlokir = "Tidak dapat menambah penerimaan periode {$selectedPeriode}. Upload bukti dukung periode {$periodeSebelumnyaDipilih} terlebih dahulu.";
                }
            }
        }

        if ($selectedPeriode) {
            $query->where('periode', $selectedPeriode);
        }

        if ($isDinkes && filled($selectedUnitBludId)) {
            $query->where('unit_blud_id', $selectedUnitBludId);
        }

        $totalDiterima = \App\Models\TransaksiPenerimaan::whereIn('penerimaan_id', (clone $query)->pluck('id'))->sum('total_biaya');
        $totalDisetorkan = \App\Models\TransaksiPenerimaan::whereIn('penerimaan_id', (clone $query)->whereNotNull('tanggal_setor')->pluck('id'))->sum('total_biaya');
        $totalSelisih = $totalDiterima - $totalDisetorkan;

        $penerimaans = $query
            ->with(['transaksis:id,penerimaan_id,nomor_tbp'])
            ->withSum('transaksis', 'total_biaya')
            ->orderByDesc('tanggal_terima')
            ->paginate(6)
            ->withQueryString();

        $buktiDukung = null;
        if ($selectedPeriode) {
            $buktiDukung = BuktiDukungPenerimaan::query()->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
                ->where('periode', $selectedPeriode)
                ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) $user->upt))])
                ->latest('id')
                ->first();
        }

        return view($viewName, compact(
            'penerimaans',
            'selectedPeriode',
            'selectedUnitBludId',
            'unitBluds',
            'buktiDukung',
            'canTambahPenerimaan',
            'alasanBlokir',
            'totalDiterima',
            'totalDisetorkan',
            'totalSelisih'
        ));
    }

    public function indexPenyetoranDinkes(Request $request)
    {
        $user = auth()->user();
        abort_unless(strtolower(trim((string) $user->upt)) === 'dinas kesehatan', 403, 'Akses ditolak.');

        $selectedPeriode = $request->get('periode');

        $query = Penerimaan::with('unitBlud')->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
            ->whereNotNull('tanggal_setor');

        if (!empty($selectedPeriode)) {
            $query->where('periode', $selectedPeriode);
        }

        $items = $query
            ->orderBy('periode')
            ->orderBy('unit_blud_id')
            ->orderByDesc('tanggal_setor')
            ->get();

        // Group: Periode -> Unit BLUD
        $grouped = $items->groupBy([
            fn ($row) => $row->periode,
            fn ($row) => optional($row->unitBlud)->nama ?? 'Unit BLUD Tidak Diketahui',
        ]);

        // Ambil link bukti dukung per periode + unit_blud_id
        $buktiLinks = [];
        foreach ($items->groupBy(fn ($r) => $r->periode . '|' . ((int) ($r->unit_blud_id ?? 0))) as $key => $rows) {
            [$periode, $unitBludId] = explode('|', $key);
            $unitBludId = (int) $unitBludId;

            $bukti = BuktiDukungPenerimaan::query()->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())
                ->where('periode', $periode)
                ->where('unit_blud_id', $unitBludId)
                ->latest('id')
                ->first();

            $buktiLinks[$key] = optional($bukti)->link;
        }

        $bulanList = [
            'Januari','Februari','Maret','April','Mei','Juni',
            'Juli','Agustus','September','Oktober','November','Desember'
        ];

        return view('penyetoran-dinkes', compact(
            'grouped',
            'selectedPeriode',
            'bulanList',
            'buktiLinks'
        ));
    }

    private function ensureTbpSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        if (\Illuminate\Support\Facades\Schema::hasTable('transaksi_penerimaans')
            && ! \Illuminate\Support\Facades\Schema::hasColumn('transaksi_penerimaans', 'nomor_tbp')) {
            \Illuminate\Support\Facades\Schema::table('transaksi_penerimaans', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('nomor_tbp', 100)->nullable()->after('nomor_transaksi');
            });
        }

        try {
            $col = \Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM `penerimaans` LIKE 'nomor_terima'");
            if ($col && strtoupper((string) ($col->Null ?? '')) === 'NO') {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE `penerimaans` MODIFY `nomor_terima` VARCHAR(100) NULL');
            }
        } catch (\Throwable $e) {
            // Ignore on non-MySQL drivers
        }
    }

    public function store(Request $request)
    {
        $this->ensureTbpSchema();

        if ($request->has('jumlah')) {
            $request->merge([
                'jumlah' => str_replace('.', '', $request->jumlah)
            ]);
        }

        $isTunai = $request->input('cara_pembayaran') === 'Tunai';
        $nomorTbpInput = $isTunai ? trim((string) $request->input('nomor_tbp', '')) : null;
        $request->merge([
            'nomor_tbp' => $nomorTbpInput !== '' ? $nomorTbpInput : null,
            'nomor_terima' => $nomorTbpInput !== '' ? $nomorTbpInput : null,
        ]);

        $bulanValid = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

        $validated = $request->validate([
            'tahun_anggaran'   => 'required|string|max:50',
            'periode'          => ['required','string','max:50', Rule::in($bulanValid)],
            'tanggal_terima'   => \App\Support\TahunAnggaran::dateRules(),
            'tanggal_setor'    => 'nullable|date',
            'cara_pembayaran'  => ['required', Rule::in([
                'Kas Penerimaan BLUD',
                'Kas BLUD',
                'Tunai',
            ])],
            'nomor_terima'     => $isTunai ? [
                'required',
                'string',
                'max:100',
                Rule::unique('penerimaans', 'nomor_terima')->whereNull('deleted_at'),
            ] : ['nullable', 'string', 'max:100'],
            'nomor_tbp'        => $isTunai ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'kode_rekening'    => 'required|string|max:100',
            'uraian'           => 'nullable|string',
        ], [
            'nomor_tbp.required' => 'Nomor TBP wajib diisi untuk cara pembayaran Tunai.',
            'nomor_terima.required' => 'Nomor TBP wajib diisi untuk cara pembayaran Tunai.',
            'nomor_terima.unique' => 'Nomor TBP tersebut sudah terdaftar.',
        ]);

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $bulanMap = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
            ];
            $bulanToAngka = array_flip($bulanMap);

            $today = Carbon::now();
            $bulanSekarangAngka = (int) $today->month;
            $periodeSekarang = $bulanMap[$bulanSekarangAngka];
            $periodeSebelumnya = $bulanMap[(int) $today->copy()->subMonth()->month];

            $periodeInput = (string) ($validated['periode'] ?? '');
            
            $periodeSebelumnyaInput = $periodeSebelumnya;
            if (isset($bulanToAngka[$periodeInput])) {
                $inputAngka = (int) $bulanToAngka[$periodeInput];
                $sebelumnyaAngka = $inputAngka - 1;
                if ($sebelumnyaAngka < 1) $sebelumnyaAngka = 12;
                $periodeSebelumnyaInput = $bulanMap[$sebelumnyaAngka];
            }

            $buktiSebelumnya = BuktiDukungPenerimaan::query()->where('tahun_anggaran', \App\Support\TahunAnggaran::selected() - ($periodeInput === 'Januari' ? 1 : 0))
                ->where('periode', $periodeSebelumnyaInput)
                ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) $user->upt))])
                ->latest('id')
                ->first();

            $linkKosong = !$buktiSebelumnya || empty(trim((string) $buktiSebelumnya->link));

            if ($linkKosong) {
                return back()->withErrors([
                    'bukti_dukung' => "Tidak dapat menambah penerimaan periode {$periodeInput}. Upload bukti dukung periode {$periodeSebelumnyaInput} terlebih dahulu.",
                ])->withInput();
            }
        }

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                return redirect()->route('penerimaan.index')
                    ->with('error', 'Unit BLUD tidak ditemukan berdasarkan UPT user.');
            }

            $validated['unit_blud_id'] = $unitBlud->id;
        } else {
            $validated['unit_blud_id'] = null;
        }
        $validated['tahun_anggaran'] = (string) \App\Support\TahunAnggaran::selected();
        $validated['akun_id'] = $user->id;

        $penerimaan = Penerimaan::create($validated);

        return redirect()->route('penerimaan.index', ['periode' => $validated['periode']])
            ->with('success', 'Penerimaan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $this->ensureTbpSchema();

        if ($request->has('jumlah')) {
            $request->merge([
                'jumlah' => str_replace('.', '', $request->jumlah)
            ]);
        }

        $penerimaan = Penerimaan::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        $isTunai = $request->input('cara_pembayaran') === 'Tunai';
        $nomorTbpInput = $isTunai ? trim((string) $request->input('nomor_tbp', '')) : null;
        $request->merge([
            'nomor_tbp' => $nomorTbpInput !== '' ? $nomorTbpInput : null,
            'nomor_terima' => $nomorTbpInput !== '' ? $nomorTbpInput : null,
        ]);

        $bulanValid = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

        $validated = $request->validate([
            'tahun_anggaran'   => 'required|string|max:50',
            'periode'          => ['required', 'string', 'max:50', Rule::in($bulanValid)],
            'tanggal_terima'   => \App\Support\TahunAnggaran::dateRules(),
            'tanggal_setor'    => 'nullable|date',
            'cara_pembayaran'  => ['required', Rule::in([
                'Kas Penerimaan BLUD',
                'Kas BLUD',
                'Tunai',
            ])],
            'nomor_terima'     => $isTunai ? [
                'required',
                'string',
                'max:100',
                Rule::unique('penerimaans', 'nomor_terima')
                    ->ignore($penerimaan->id)
                    ->whereNull('deleted_at'),
            ] : ['nullable', 'string', 'max:100'],
            'nomor_tbp'        => $isTunai ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'kode_rekening'    => 'required|string|max:100',
            'uraian'           => 'nullable|string',
        ], [
            'nomor_tbp.required' => 'Nomor TBP wajib diisi untuk cara pembayaran Tunai.',
            'nomor_terima.required' => 'Nomor TBP wajib diisi untuk cara pembayaran Tunai.',
            'nomor_terima.unique' => 'Nomor TBP tersebut sudah terdaftar.',
        ]);

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                return redirect()->route('penerimaan.index')
                    ->with('error', 'Unit BLUD tidak ditemukan berdasarkan UPT user.');
            }

            if ((int) $penerimaan->unit_blud_id !== (int) $unitBlud->id) {
                return redirect()->route('penerimaan.index')
                    ->with('error', 'Data penerimaan tidak sesuai dengan unit BLUD Anda.');
            }

            $validated['unit_blud_id'] = $unitBlud->id;
        } else {
            $validated['unit_blud_id'] = $penerimaan->unit_blud_id;
        }
        $validated['tahun_anggaran'] = (string) \App\Support\TahunAnggaran::selected();
        $validated['akun_id'] = $user->id;

        $penerimaan->update($validated);

        return redirect()->route('penerimaan.index', ['periode' => $validated['periode']])
            ->with('success', 'Penerimaan berhasil diperbarui');
    }


    public function updateNomorTbp(Request $request, $id)
    {
        $nomorTbp = $request->input('nomor_tbp', $request->input('value'));
        $request->merge(['nomor_tbp' => $nomorTbp]);

        $validated = $request->validate([
            'nomor_tbp' => 'nullable|string|max:100',
        ]);

        $penerimaan = Penerimaan::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        $nomorLamaKosong = empty(trim((string) $penerimaan->nomor_tbp));
        $nomorBaruTerisi = !empty(trim((string) ($validated['nomor_tbp'] ?? '')));

        $penerimaan->nomor_tbp = $validated['nomor_tbp'];

        // Set tanggal_setor hanya saat pertama kali nomor_tbp diisi
        if ($nomorLamaKosong && $nomorBaruTerisi && empty($penerimaan->tanggal_setor)) {
            $penerimaan->tanggal_setor = now()->toDateString(); // YYYY-MM-DD
        }

        $penerimaan->save();

        return response()->json([
            'success' => true,
            'message' => 'Nomor TBP berhasil diperbarui.',
            'tanggal_setor' => $penerimaan->tanggal_setor,
        ]);
    }

    public function updateTanggalSetor(Request $request, $id)
    {
        $validated = $request->validate([
            'tanggal_setor' => 'required|date',
        ]);

        $penerimaan = Penerimaan::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);
        $penerimaan->tanggal_setor = $validated['tanggal_setor'];
        $penerimaan->save();

        return back()->with('success', 'Tanggal setor berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $penerimaan = Penerimaan::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);
        $periode = $penerimaan->periode;
        $penerimaan->delete();

        return redirect()->route('penerimaan.index', ['periode' => $periode])
            ->with('success', 'Penerimaan berhasil dihapus');
    }

    public function search(Request $request)
    {
        $search = $request->q;

        $data = Akun::where('nama', 'like', "%$search%")
            ->limit(20)
            ->get();

        return response()->json(
            $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->uraian
                ];
            })
        );
    }
}
