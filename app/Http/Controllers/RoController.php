<?php

namespace App\Http\Controllers;

use App\Models\RencanaKebutuhan;
use App\Models\Akun;
use App\Models\RincianObjek;
use App\Models\UnitBlud;
use App\Support\TahunRba;
use App\Support\PembiayaanValidator;
use App\Models\BkuBendaharaPengeluaran;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoController extends Controller
{
    private function isPrivilegedRole(\App\Models\User $user): bool
    {
        return $user->hasAnyRole(['superuser', 'kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes']);
    }

    /**
     * Kembalikan prefix klasifikasi belanja dari kode rekening.
     * Tiga prefix utama: '5.1.01', '5.1.02', '5.2'
     * Perpindahan antar prefix yang berbeda → wajib verifikasi Dinas Kesehatan.
     */
    private function getKlasifikasiPrefix(string $kode_rek): string
    {
        $parts = explode('.', $kode_rek);
        if (count($parts) >= 3) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2];
        }
        return $kode_rek;
    }

    /**
     * Tentukan verifikator berdasarkan prefix sumber dan tujuan.
     * Jika prefix berbeda (lintas klasifikasi) → Dinas Kesehatan.
     * Jika prefix sama (dalam klasifikasi yang sama) → Kepala Puskesmas.
     */
    private function getVerifikatorPerubahan(string $sumberKodeRek, string $tujuanKodeRek): string
    {
        return ($this->getKlasifikasiPrefix($sumberKodeRek) === $this->getKlasifikasiPrefix($tujuanKodeRek))
            ? 'kepala puskesmas'
            : 'dinas kesehatan';
    }

    /** Pastikan pagu usulan per rekening tetap menutup realisasi BKU. */
    private function pastikanSisaPaguBelanja(RencanaKebutuhan $sumber, array $nilaiPerRincian, array $nilaiBaruPerRekening = [], string $field = 'jumlah'): void
    {
        $rincian = RencanaKebutuhan::with('akuns')
            ->where('tahun_anggaran', $sumber->tahun_anggaran)
            ->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim($sumber->nama_blud))])
            ->whereNotNull('akun_id')->lockForUpdate()->get();
        $paguUsulan = [];
        $paguAktif = [];
        $rekeningTerdampak = [];
        foreach ($rincian as $item) {
            $kode = $item->akuns?->kode_rek;
            if (!$kode) continue;
            $nilai = $nilaiPerRincian[$item->id]
                ?? (in_array($item->status_perubahan, ['pending', 'verified'], true) && $item->jumlah_harga_perubahan !== null
                    ? $item->jumlah_harga_perubahan : $item->jumlah_harga);
            $paguUsulan[$kode] = ($paguUsulan[$kode] ?? 0) + (int) $nilai;
            $paguAktif[$kode] = ($paguAktif[$kode] ?? 0) + (int) $item->active_jumlah_harga;
            if (array_key_exists($item->id, $nilaiPerRincian)) $rekeningTerdampak[$kode] = true;
        }
        foreach ($nilaiBaruPerRekening as $kode => $nilai) {
            $paguUsulan[$kode] = ($paguUsulan[$kode] ?? 0) + (int) $nilai;
            $rekeningTerdampak[$kode] = true;
        }

        $unitId = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim($sumber->nama_blud))])->value('id');
        if (!$unitId) {
            throw ValidationException::withMessages([$field => 'Unit BLUD RBA belum terdaftar.']);
        }
        foreach (array_keys($rekeningTerdampak) as $kode) {
            $realisasi = BkuBendaharaPengeluaran::where('unit_blud_id', $unitId)
                ->where('tanggal', '>=', $sumber->tahun_anggaran.'-01-01')
                ->where('tanggal', '<', ((int) $sumber->tahun_anggaran + 1).'-01-01')
                ->where('kode_rekening', $kode)->whereNull('parent_bku_id')
                ->where('is_saldo_awal', false)->sum('pengeluaran');
            if (($paguUsulan[$kode] ?? 0) < $realisasi) {
                throw ValidationException::withMessages([$field => 'Pagu usulan rekening '.$kode.' (Rp '
                    .number_format($paguUsulan[$kode] ?? 0, 0, ',', '.').') lebih kecil dari realisasi BKU (Rp '
                    .number_format($realisasi, 0, ',', '.').'). Sisa anggaran saat ini: Rp '
                    .number_format(max(0, ($paguAktif[$kode] ?? 0) - $realisasi), 0, ',', '.').'.']);
            }
        }
    }

    private function getRbaData(array $klasifikasi, bool $isPerubahan = false, bool $onlyVerifiedChanges = false)
    {
        $user = auth()->user();
        $tahunRba = TahunRba::selected();
        $query = RencanaKebutuhan::with('akuns')->whereNotNull('akun_id')->where('tahun_anggaran', $tahunRba);

        // Semua user kecuali Superuser hanya melihat data BLUD mereka sendiri
        if (!$user->hasRole('Superuser')) {
            $query->where('nama_blud', $user->upt);
        }

        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        // Halaman RBA Belanja biasa (murni) tidak menampilkan record hasil perubahan lintas akun
        if (!$isPerubahan) {
            $query->where(function ($q) {
                $q->where('is_hasil_perubahan', false)
                  ->orWhereNull('is_hasil_perubahan');
            });
        }

        $renbut = $query->get();

        if ($onlyVerifiedChanges) {
            $renbut = $renbut->reject(function ($item) {
                return $item->is_hasil_perubahan && $item->status_perubahan !== 'verified';
            });

            $renbut->transform(function ($item) {
                if ($item->status_perubahan !== 'verified') {
                    $item->uraian_perubahan = null;
                    $item->jumlah_perubahan = null;
                    $item->satuan_perubahan = null;
                    $item->harga_satuan_perubahan = null;
                    $item->jumlah_harga_perubahan = null;
                    $item->keterangan_perubahan = null;
                    $item->status_perubahan = null;
                }
                return $item;
            });
        }

        $grouped = $renbut->groupBy('akun_id');

        // Kelompokkan berdasarkan klasifikasi — satu kali iterasi
        $groupedByKlasifikasi = [];
        $classifiedKeys = [];
        foreach ($klasifikasi as $key => $klas) {
            $groupedByKlasifikasi[$key] = $grouped->filter(function ($items, $akun_id) use ($klas, &$classifiedKeys) {
                $kodeRek = optional($items->first()->akuns)->kode_rek ?? '';
                if (str_starts_with($kodeRek, $klas['prefix'])) {
                    $classifiedKeys[] = $akun_id;
                    return true;
                }
                return false;
            });
        }
        $groupedByKlasifikasi['lainnya'] = $grouped->reject(fn($items, $akun_id) => in_array($akun_id, $classifiedKeys));

        // Hitung semua total dalam satu pass
        $grandTotalJumlah = 0;
        $total_5          = 0;
        $total_5_1        = 0;
        $total_5_2        = 0;
        foreach ($renbut as $item) {
            $kode = optional($item->akuns)->kode_rek ?? '';
            $jh   = (float) ($isPerubahan ? $item->proposed_jumlah_harga : $item->jumlah_harga);
            $grandTotalJumlah += $jh;
            if (str_starts_with($kode, '5'))   $total_5   += $jh;
            if (str_starts_with($kode, '5.1')) $total_5_1 += $jh;
            if (str_starts_with($kode, '5.2')) $total_5_2 += $jh;
        }

        $puskesmasList = [];
        if ($user->hasRole('Superuser')) {
            $puskesmasList = UnitBlud::orderBy('nama')->get();
        }

        return compact('grouped', 'groupedByKlasifikasi', 'klasifikasi', 'grandTotalJumlah', 'total_5', 'total_5_1', 'total_5_2', 'puskesmasList', 'tahunRba');
    }

    private function getPendapatanData(array $klasifikasi, bool $isPerubahan = false, bool $onlyVerifiedChanges = false)
    {
        $user = auth()->user();
        $tahunRba = TahunRba::selected();
        $query = \App\Models\RencanaPendapatan::with('jenisPelayanans')->select(['id', 'kode_rek', 'uraian', 'jumlah', 'nama_blud', 'uraian_perubahan', 'jumlah_perubahan', 'status_perubahan', 'verifikator_perubahan', 'is_hasil_perubahan', 'jenis_pelayanan_perubahan', 'rekening_awal', 'rekening_akhir', 'pergeseran_id']);

        $query->addSelect('tahun_anggaran')->where('tahun_anggaran', $tahunRba);

        // Halaman RBA Pendapatan biasa (murni) tidak menampilkan record hasil perubahan
        if (!$isPerubahan) {
            $query->where(function ($q) {
                $q->where('is_hasil_perubahan', false)
                  ->orWhereNull('is_hasil_perubahan');
            });
        }

        // Semua user kecuali Superuser hanya melihat data BLUD mereka sendiri
        if (!$user->hasRole('Superuser') && !($isPerubahan && \App\Support\AlurPerubahanPendapatan::isDinas($user))) {
            $query->where('nama_blud', $user->upt);
        }

        if (($user->hasRole('Superuser') || ($isPerubahan && \App\Support\AlurPerubahanPendapatan::isDinas($user))) && request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        $pendapatan = $query->get();

        if ($onlyVerifiedChanges) {
            $pendapatan = $pendapatan->reject(function ($item) {
                return $item->is_hasil_perubahan && $item->status_perubahan !== 'verified';
            });

            $pendapatan->transform(function ($item) {
                if ($item->status_perubahan !== 'verified') {
                    $item->uraian_perubahan = null;
                    $item->jumlah_perubahan = null;
                    $item->jenis_pelayanan_perubahan = null;
                    $item->status_perubahan = null;
                }
                return $item;
            });
        }

        $grouped = $pendapatan->groupBy('kode_rek');

        $groupedByKlasifikasi = [];
        $classifiedKeys = [];
        foreach ($klasifikasi as $key => $klas) {
            $groupedByKlasifikasi[$key] = $grouped->filter(function ($items, $kode_rek) use ($klas, &$classifiedKeys) {
                if (str_starts_with((string)$kode_rek, $klas['prefix'])) {
                    $classifiedKeys[] = $kode_rek;
                    return true;
                }
                return false;
            });
        }
        $groupedByKlasifikasi['lainnya'] = $grouped->reject(fn($items, $kode_rek) => in_array($kode_rek, $classifiedKeys));

        $grandTotalJumlah = 0;
        foreach ($pendapatan as $item) {
            $j = (float) ($isPerubahan ? ($item->jumlah_perubahan ?? $item->jumlah) : $item->jumlah);
            $grandTotalJumlah += $j;
        }

        $puskesmasList = [];
        if ($user->hasRole('Superuser') || ($isPerubahan && \App\Support\AlurPerubahanPendapatan::isDinas($user))) {
            $puskesmasList = UnitBlud::orderBy('nama')->get();
        }

        return compact('grouped', 'groupedByKlasifikasi', 'klasifikasi', 'grandTotalJumlah', 'puskesmasList', 'tahunRba');
    }

    private function getPembiayaanData(array $klasifikasi, bool $isPerubahan = false, bool $onlyVerifiedChanges = false)
    {
        $user = auth()->user();
        $tahunRba = TahunRba::selected();
        $pembiayaanFilters = $this->pembiayaanFilters();
        $query = \App\Models\RencanaPembiayaan::query()->where('tahun_anggaran', $tahunRba);

        // Semua user kecuali Superuser hanya melihat data BLUD mereka sendiri
        if (!$user->hasRole('Superuser')) {
            $query->where('nama_blud', $user->upt);
        }

        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        $pembiayaan = $query->get();

        if ($onlyVerifiedChanges) {
            $pembiayaan = $pembiayaan->reject(function ($item) {
                return isset($item->is_hasil_perubahan) && $item->is_hasil_perubahan && $item->status_perubahan !== 'verified';
            });

            $pembiayaan->transform(function ($item) {
                if ($item->status_perubahan !== 'verified') {
                    foreach (array_keys($item->rincian()) as $field) $item->setAttribute($field.'_perubahan', null);
                    $item->status_perubahan = null;
                } elseif ($item->kode_rek_perubahan !== null) {
                    $item->kode_rek_awal = $item->kode_rek;
                    $item->kode_rek = $item->kode_rek_perubahan;
                }
                return $item;
            });
        }

        $grouped = $pembiayaan->groupBy('kode_rek');

        $groupedByKlasifikasi = [];
        $classifiedKeys = [];
        foreach ($klasifikasi as $key => $klas) {
            $groupedByKlasifikasi[$key] = $grouped->filter(function ($items, $kode_rek) use ($klas, &$classifiedKeys) {
                if (str_starts_with((string)$kode_rek, $klas['prefix'])) {
                    $classifiedKeys[] = $kode_rek;
                    return true;
                }
                return false;
            });
        }
        $groupedByKlasifikasi['lainnya'] = $grouped->reject(fn($items, $kode_rek) => in_array($kode_rek, $classifiedKeys));

        $grandTotalJumlah = 0;
        foreach ($pembiayaan as $item) {
            $j = (float) ($isPerubahan ? ($item->jumlah_perubahan ?? $item->jumlah) : $item->jumlah);
            $grandTotalJumlah += $j;
        }

        $puskesmasList = [];
        if ($user->hasRole('Superuser')) {
            $puskesmasList = UnitBlud::orderBy('nama')->get();
        }

        return compact('grouped', 'groupedByKlasifikasi', 'klasifikasi', 'grandTotalJumlah', 'puskesmasList', 'isPerubahan', 'tahunRba', 'pembiayaanFilters');
    }

    public function index()
    {
        $klasifikasi = [
            'belanja_pegawai'           => ['prefix' => '5.1.01', 'label' => '5.1.01 - BELANJA PEGAWAI BLUD',                     'color' => 'table-primary'],
            'belanja_barang_jasa'       => ['prefix' => '5.1.02', 'label' => '5.1.02 - BELANJA BARANG DAN JASA BLUD',             'color' => 'table-info'],
            'belanja_modal_peralatan'   => ['prefix' => '5.2.02', 'label' => '5.2.02 - BELANJA MODAL PERALATAN DAN MESIN BLUD',   'color' => 'table-warning'],
            'belanja_modal_gedung'      => ['prefix' => '5.2.03', 'label' => '5.2.03 - BELANJA MODAL GEDUNG DAN BANGUNAN BLUD',   'color' => 'table-success'],
            'belanja_modal_jalan'       => ['prefix' => '5.2.04', 'label' => '5.2.04 - BELANJA MODAL JALAN, JARINGAN DAN IRIGASI BLUD', 'color' => 'table-secondary'],
        ];

        $data = $this->getRbaData($klasifikasi);
        
        $tahun = TahunRba::selected();
        
        $uptName = auth()->user()->upt;
        if (auth()->user()->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }
        
        $unitBlud = \App\Models\UnitBlud::where('nama', $uptName)->first();
        $data['kinerjaKegiatan'] = $unitBlud ? $unitBlud->kinerjaKegiatans()->where('tahun', $tahun)->first() : null;
        $data['tahun'] = $tahun;

        return view('rba-belanja', $data);
    }

    public function storeKinerjaKegiatan(Request $request)
    {
        $request->validate([
            'tahun' => 'required',
            'capaian_program_tolak_ukur' => 'nullable|string',
            'capaian_program_target' => 'nullable|string',
            'masukan_tolak_ukur' => 'nullable|string',
            'masukan_target' => 'nullable|string',
            'keluaran_tolak_ukur' => 'nullable|string',
            'keluaran_target' => 'nullable|string',
        ]);

        $unitBlud = \App\Models\UnitBlud::where('nama', auth()->user()->upt)->first();

        if ($unitBlud) {
            $unitBlud->kinerjaKegiatans()->updateOrCreate(
                ['tahun' => $request->tahun],
                [
                    'capaian_program_tolak_ukur' => $request->capaian_program_tolak_ukur,
                    'capaian_program_target' => $request->capaian_program_target,
                    'masukan_tolak_ukur' => $request->masukan_tolak_ukur,
                    'masukan_target' => $request->masukan_target,
                    'keluaran_tolak_ukur' => $request->keluaran_tolak_ukur,
                    'keluaran_target' => $request->keluaran_target,
                ]
            );
        }

        return redirect()->back()->with('success', 'Kinerja Kegiatan berhasil disimpan.');
    }

    public function belanjaPerubahan()
    {
        $klasifikasi = [
            'belanja_pegawai'           => ['prefix' => '5.1.01', 'label' => '5.1.01 - BELANJA PEGAWAI BLUD',                     'color' => 'table-primary'],
            'belanja_barang_jasa'       => ['prefix' => '5.1.02', 'label' => '5.1.02 - BELANJA BARANG DAN JASA BLUD',             'color' => 'table-info'],
            'belanja_modal_peralatan'   => ['prefix' => '5.2.02', 'label' => '5.2.02 - BELANJA MODAL PERALATAN DAN MESIN BLUD',   'color' => 'table-warning'],
            'belanja_modal_gedung'      => ['prefix' => '5.2.03', 'label' => '5.2.03 - BELANJA MODAL GEDUNG DAN BANGUNAN BLUD',   'color' => 'table-success'],
            'belanja_modal_jalan'       => ['prefix' => '5.2.04', 'label' => '5.2.04 - BELANJA MODAL JALAN, JARINGAN DAN IRIGASI BLUD', 'color' => 'table-secondary'],
        ];
        
        $data = $this->getRbaData($klasifikasi, true);
        $data['allAkuns'] = \App\Models\Akun::where('kode_rek', 'like', '5%')->orderBy('kode_rek')->get();
        
        $tahun = TahunRba::selected();
        $uptName = auth()->user()->upt;
        if (auth()->user()->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }
        
        $unitBlud = \App\Models\UnitBlud::where('nama', $uptName)->first();
        $data['kinerjaKegiatan'] = $unitBlud ? $unitBlud->kinerjaKegiatans()->where('tahun', $tahun)->first() : null;
        $data['tahun'] = $tahun;

        return view('rba-belanja-perubahan', $data);
    }

    public function storeKinerjaKegiatanPerubahan(Request $request)
    {
        $request->validate([
            'tahun' => 'required',
            'capaian_program_tolak_ukur_perubahan' => 'nullable|string',
            'capaian_program_target_perubahan' => 'nullable|string',
            'masukan_tolak_ukur_perubahan' => 'nullable|string',
            'masukan_target_perubahan' => 'nullable|string',
            'keluaran_tolak_ukur_perubahan' => 'nullable|string',
            'keluaran_target_perubahan' => 'nullable|string',
        ]);

        $unitBlud = \App\Models\UnitBlud::where('nama', auth()->user()->upt)->first();

        if ($unitBlud) {
            $kinerja = $unitBlud->kinerjaKegiatans()->where('tahun', $request->tahun)->first();
            if ($kinerja) {
                $kinerja->update([
                    'capaian_program_tolak_ukur_perubahan' => $request->capaian_program_tolak_ukur_perubahan,
                    'capaian_program_target_perubahan' => $request->capaian_program_target_perubahan,
                    'masukan_tolak_ukur_perubahan' => $request->masukan_tolak_ukur_perubahan,
                    'masukan_target_perubahan' => $request->masukan_target_perubahan,
                    'keluaran_tolak_ukur_perubahan' => $request->keluaran_tolak_ukur_perubahan,
                    'keluaran_target_perubahan' => $request->keluaran_target_perubahan,
                    'status_perubahan' => 'pending',
                    'verifikator_perubahan' => 'kepala puskesmas', // default verifikator
                ]);
            } else {
                // If doesn't exist, they should create it in Murni first. But we can create if needed.
                $unitBlud->kinerjaKegiatans()->create([
                    'tahun' => $request->tahun,
                    'capaian_program_tolak_ukur_perubahan' => $request->capaian_program_tolak_ukur_perubahan,
                    'capaian_program_target_perubahan' => $request->capaian_program_target_perubahan,
                    'masukan_tolak_ukur_perubahan' => $request->masukan_tolak_ukur_perubahan,
                    'masukan_target_perubahan' => $request->masukan_target_perubahan,
                    'keluaran_tolak_ukur_perubahan' => $request->keluaran_tolak_ukur_perubahan,
                    'keluaran_target_perubahan' => $request->keluaran_target_perubahan,
                    'status_perubahan' => 'pending',
                    'verifikator_perubahan' => 'kepala puskesmas',
                ]);
            }
        }

        return redirect()->back()->with('success', 'Perubahan Kinerja Kegiatan berhasil disimpan dan menunggu verifikasi.');
    }

    public function verifikasiKinerjaKegiatanPerubahan($id)
    {
        $kinerja = \App\Models\KinerjaKegiatan::findOrFail($id);
        $kinerja->update([
            'status_perubahan' => 'verified',
        ]);

        return redirect()->back()->with('success', 'Perubahan Kinerja Kegiatan berhasil diverifikasi.');
    }

    public function tolakKinerjaKegiatanPerubahan($id)
    {
        $kinerja = \App\Models\KinerjaKegiatan::findOrFail($id);
        $kinerja->update([
            'status_perubahan' => 'rejected',
        ]);

        return redirect()->back()->with('success', 'Perubahan Kinerja Kegiatan berhasil ditolak.');
    }

    public function updateBelanjaPerubahan(Request $request, $id)
    {
        $request->validate([
            'uraian' => 'required',
            'jumlah' => 'required|numeric|min:0',
            'satuan' => 'required',
            'harga_satuan' => 'required|numeric|min:0',
            'keterangan' => 'nullable',
        ]);

        DB::transaction(function () use ($request, $id) {
            $renbut = RencanaKebutuhan::where('tahun_anggaran', TahunRba::selected())
                ->lockForUpdate()->findOrFail($id);
            abort_if(!auth()->user()->hasRole('Superuser') && strcasecmp($renbut->nama_blud, auth()->user()->upt) !== 0, 404);
            $jumlahHarga = $request->jumlah * $request->harga_satuan;
            $this->pastikanSisaPaguBelanja($renbut, [$renbut->id => $jumlahHarga]);
            $renbut->update([
                'uraian_perubahan' => $request->uraian,
                'jumlah_perubahan' => $request->jumlah,
                'satuan_perubahan' => $request->satuan,
                'harga_satuan_perubahan' => $request->harga_satuan,
                'jumlah_harga_perubahan' => $jumlahHarga,
                'keterangan_perubahan' => $request->keterangan,
                'status_perubahan' => 'pending',
            ]);
        });

        return redirect()->route('rba-perubahan.belanja')
            ->with('success', 'Perubahan Rencana Belanja berhasil disimpan');
    }

    public function zeroOutBelanjaPerubahan($id)
    {
        DB::transaction(function () use ($id) {
            $renbut = RencanaKebutuhan::where('tahun_anggaran', TahunRba::selected())
                ->lockForUpdate()->findOrFail($id);
            abort_if(!auth()->user()->hasRole('Superuser') && strcasecmp($renbut->nama_blud, auth()->user()->upt) !== 0, 404);
            $this->pastikanSisaPaguBelanja($renbut, [$renbut->id => 0]);
            $renbut->update([
                'uraian_perubahan' => $renbut->uraian_perubahan ?? $renbut->uraian,
                'jumlah_perubahan' => 0,
                'satuan_perubahan' => $renbut->satuan_perubahan ?? $renbut->satuan,
                'harga_satuan_perubahan' => 0,
                'jumlah_harga_perubahan' => 0,
                'keterangan_perubahan' => $renbut->keterangan_perubahan ?? $renbut->keterangan,
                'status_perubahan' => 'pending',
            ]);
        });

        return redirect()->route('rba-perubahan.belanja')
            ->with('success', 'Perubahan Rencana Belanja berhasil dihapus (nilai perubahan diubah menjadi 0 dan berstatus pending).');
    }

    public function verifikasiBelanjaPerubahan($id)
    {
        DB::transaction(function () use ($id) {
            $renbut = RencanaKebutuhan::where('tahun_anggaran', TahunRba::selected())
                ->lockForUpdate()->findOrFail($id);
            $this->pastikanSisaPaguBelanja($renbut,
                [$renbut->id => $renbut->jumlah_harga_perubahan ?? $renbut->jumlah_harga], [], 'jumlah_harga_perubahan');
            $user = auth()->user();
            $isLintasPrefix = ($this->getKlasifikasiPrefix($renbut->rekening_awal ?? '') !== $this->getKlasifikasiPrefix($renbut->rekening_akhir ?? ''));

            if ($user->hasRole('kepala puskesmas') && !$user->hasRole('Superuser') && $isLintasPrefix && ($renbut->verifikator_perubahan ?? 'kepala puskesmas') === 'kepala puskesmas') {
                $renbut->update(['verifikator_perubahan' => 'dinas kesehatan']);
            } else {
                $renbut->update(['status_perubahan' => 'verified']);
            }
        });

        return redirect()->route('rba-perubahan.belanja')
            ->with('success', 'Perubahan Rencana Belanja berhasil diverifikasi.');
    }

    public function verifikasiSemuaBelanjaPerubahan()
    {
        $user = auth()->user();
        $roles = array_map(fn ($role) => strtolower($role), $user->roleNames());
        $query = RencanaKebutuhan::where('tahun_anggaran', \App\Support\TahunRba::selected())->where('status_perubahan', 'pending');
        
        if (!$user->hasRole('Superuser')) {
            // Dinas Kesehatan berwenang atas semua puskesmas, tidak dibatasi nama_blud
            if (!$user->hasAnyRole(['dinas', 'dinas kesehatan'])) {
                $query->where('nama_blud', $user->upt);
            }
            
            // Filter berdasarkan wewenang verifikator
            $query->where(function($q) use ($roles, $user) {
                if ($user->hasAnyRole(['dinas', 'dinas kesehatan'])) $roles[] = 'dinas kesehatan';
                $q->whereIn('verifikator_perubahan', $roles);
                // Backward compatibility: jika Kepala Puskesmas, juga ambil yang null
                if ($user->hasRole('kepala puskesmas')) {
                    $q->orWhereNull('verifikator_perubahan');
                }
            });
        }

        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        DB::transaction(function () use ($query, $user) {
            $items = $query->lockForUpdate()->get();
            foreach ($items as $item) {
                $this->pastikanSisaPaguBelanja($item,
                    [$item->id => $item->jumlah_harga_perubahan ?? $item->jumlah_harga], [], 'jumlah_harga_perubahan');
            }
            foreach ($items as $item) {
                $isLintasPrefix = ($this->getKlasifikasiPrefix($item->rekening_awal ?? '') !== $this->getKlasifikasiPrefix($item->rekening_akhir ?? ''));
                if ($user->hasRole('kepala puskesmas') && !$user->hasRole('Superuser') && $isLintasPrefix && ($item->verifikator_perubahan === 'kepala puskesmas' || is_null($item->verifikator_perubahan))) {
                    $item->update(['verifikator_perubahan' => 'dinas kesehatan']);
                } else {
                    $item->update(['status_perubahan' => 'verified']);
                }
            }
        });

        return redirect()->route('rba-perubahan.belanja')
            ->with('success', 'Semua Perubahan Rencana Belanja berhasil diverifikasi.');
    }

    public function tolakSemuaBelanjaPerubahan()
    {
        $user = auth()->user();
        $roles = array_map(fn ($role) => strtolower($role), $user->roleNames());
        $query = RencanaKebutuhan::where('tahun_anggaran', \App\Support\TahunRba::selected())->where('status_perubahan', 'pending');
        
        if (!$user->hasRole('Superuser')) {
            // Dinas Kesehatan berwenang atas semua puskesmas, tidak dibatasi nama_blud
            if (!$user->hasAnyRole(['dinas', 'dinas kesehatan'])) {
                $query->where('nama_blud', $user->upt);
            }
            
            // Filter berdasarkan wewenang verifikator
            $query->where(function($q) use ($roles, $user) {
                if ($user->hasAnyRole(['dinas', 'dinas kesehatan'])) $roles[] = 'dinas kesehatan';
                $q->whereIn('verifikator_perubahan', $roles);
                // Backward compatibility: jika Kepala Puskesmas, juga ambil yang null
                if ($user->hasRole('kepala puskesmas')) {
                    $q->orWhereNull('verifikator_perubahan');
                }
            });
        }

        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        $query->update([
            'uraian_perubahan'       => null,
            'jumlah_perubahan'       => null,
            'satuan_perubahan'       => null,
            'harga_satuan_perubahan' => null,
            'jumlah_harga_perubahan' => null,
            'keterangan_perubahan'   => null,
            'status_perubahan'       => null,
            'verifikator_perubahan'  => null,
            'rekening_awal'          => null,
            'rekening_akhir'         => null,
        ]);

        return redirect()->route('rba-perubahan.belanja')
            ->with('success', 'Semua Perubahan Rencana Belanja berhasil ditolak.');
    }

    public function createPergeseranLintasAkun()
    {
        $klasifikasi = [
            'belanja_pegawai'           => ['prefix' => '5.1.01', 'label' => '5.1.01 - BELANJA PEGAWAI BLUD',                     'color' => 'table-primary'],
            'belanja_barang_jasa'       => ['prefix' => '5.1.02', 'label' => '5.1.02 - BELANJA BARANG DAN JASA BLUD',             'color' => 'table-info'],
            'belanja_modal_peralatan'   => ['prefix' => '5.2.02', 'label' => '5.2.02 - BELANJA MODAL PERALATAN DAN MESIN BLUD',   'color' => 'table-warning'],
            'belanja_modal_gedung'      => ['prefix' => '5.2.03', 'label' => '5.2.03 - BELANJA MODAL GEDUNG DAN BANGUNAN BLUD',   'color' => 'table-success'],
            'belanja_modal_jalan'       => ['prefix' => '5.2.04', 'label' => '5.2.04 - BELANJA MODAL JALAN, JARINGAN DAN IRIGASI BLUD', 'color' => 'table-secondary'],
        ];
        
        $data = $this->getRbaData($klasifikasi, true);
        $data['allAkuns'] = \App\Models\Akun::where('kode_rek', 'like', '5%')->orderBy('kode_rek')->get();
        $rincian = $data['grouped']->flatten();
        $unitIds = UnitBlud::all()->keyBy(fn ($unit) => strtolower(trim($unit->nama)));
        $relevantUnitIds = $rincian->pluck('nama_blud')
            ->map(fn ($nama) => $unitIds->get(strtolower(trim($nama)))?->id)->filter()->unique();
        $relevantCodes = $rincian->map(fn ($item) => $item->akuns?->kode_rek)->filter()->unique();
        $paguRekening = $rincian->groupBy(fn ($item) => strtolower(trim($item->nama_blud)).'|'.($item->akuns?->kode_rek ?? ''))
            ->map(fn ($items) => (int) $items->sum('active_jumlah_harga'));
        $realisasiRekening = BkuBendaharaPengeluaran::query()
            ->whereIn('unit_blud_id', $relevantUnitIds)->whereIn('kode_rekening', $relevantCodes)
            ->where('tanggal', '>=', TahunRba::selected().'-01-01')
            ->where('tanggal', '<', (TahunRba::selected() + 1).'-01-01')
            ->whereNull('parent_bku_id')->where('is_saldo_awal', false)
            ->selectRaw('unit_blud_id, kode_rekening, SUM(pengeluaran) AS total')
            ->groupBy('unit_blud_id', 'kode_rekening')->get()
            ->keyBy(fn ($row) => $row->unit_blud_id.'|'.$row->kode_rekening);
        $data['paguBkuPerRincian'] = $rincian->mapWithKeys(function ($item) use ($unitIds, $paguRekening, $realisasiRekening) {
            $unitKey = strtolower(trim($item->nama_blud));
            $kode = $item->akuns?->kode_rek ?? '';
            $pagu = $paguRekening->get($unitKey.'|'.$kode, 0);
            $unitId = $unitIds->get($unitKey)?->id;
            $realisasi = (int) ($realisasiRekening->get($unitId.'|'.$kode)?->total ?? 0);
            return [$item->id => ['pagu' => $pagu, 'realisasi' => $realisasi, 'sisa' => max(0, $pagu - $realisasi)]];
        });
        
        return view('rba-belanja-perubahan-form', $data);
    }

    public function pergeseranLintasAkun(Request $request)
    {
        $request->validate([
            'sumber_renbut_id'   => 'required|exists:rencana_kebutuhans,id',
            'sumber_uraian'      => 'required|string',
            'sumber_jumlah'      => 'required|numeric|min:0',
            'sumber_satuan'      => 'required|string',
            'sumber_harga_satuan'=> 'required|numeric|min:0',
            'tipe_tujuan'        => 'required|in:existing,baru',
        ]);

        if ($request->tipe_tujuan === 'existing') {
            $request->validate([
                'tujuan_renbut_id'   => 'required|exists:rencana_kebutuhans,id',
                'tujuan_akun_id'     => 'required|exists:akuns,id',
                'tujuan_uraian'      => 'required',
                'tujuan_jumlah'      => 'required|numeric|min:0',
                'tujuan_satuan'      => 'required',
                'tujuan_harga_satuan'=> 'required|numeric|min:0',
            ]);
            $akunId = $request->tujuan_akun_id;
            $tujuanUraian = $request->tujuan_uraian;
            $tujuanJumlah = $request->tujuan_jumlah;
            $tujuanSatuan = $request->tujuan_satuan;
            $tujuanHarga = $request->tujuan_harga_satuan;
            $tujuanKeterangan = $request->tujuan_keterangan;
        } else {
            $request->validate([
                'tujuan_akun_id_baru'     => 'required|exists:akuns,id',
                'tujuan_uraian_baru'      => 'required',
                'tujuan_jumlah_baru'      => 'required|numeric|min:0',
                'tujuan_satuan_baru'      => 'required',
                'tujuan_harga_satuan_baru'=> 'required|numeric|min:0',
            ]);
            $akunId = $request->tujuan_akun_id_baru;
            $tujuanUraian = $request->tujuan_uraian_baru;
            $tujuanJumlah = $request->tujuan_jumlah_baru;
            $tujuanSatuan = $request->tujuan_satuan_baru;
            $tujuanHarga = $request->tujuan_harga_satuan_baru;
            $tujuanKeterangan = $request->tujuan_keterangan_baru;
        }

        DB::transaction(function () use ($request, $akunId, $tujuanUraian, $tujuanJumlah, $tujuanSatuan, $tujuanHarga, $tujuanKeterangan) {
            $sumber = RencanaKebutuhan::where('tahun_anggaran', TahunRba::selected())
                ->lockForUpdate()->findOrFail($request->sumber_renbut_id);
            abort_if(!auth()->user()->hasRole('Superuser') && strcasecmp($sumber->nama_blud, auth()->user()->upt) !== 0, 404);
            $sumberAkun = $sumber->akuns;
            $tujuanAkun = Akun::findOrFail($akunId);
            $tujuan = $request->tipe_tujuan === 'existing'
                ? RencanaKebutuhan::where('tahun_anggaran', TahunRba::selected())
                    ->lockForUpdate()->findOrFail($request->tujuan_renbut_id)
                : null;
            if ($tujuan && ($tujuan->id === $sumber->id || $tujuan->akun_id !== $tujuanAkun->id
                || strcasecmp($tujuan->nama_blud, $sumber->nama_blud) !== 0)) {
                throw ValidationException::withMessages(['tujuan_renbut_id' => 'Rincian tujuan harus berbeda dan berada pada rekening serta unit BLUD yang dipilih.']);
            }
    
            // Simpan kode rekening sumber & tujuan secara eksplisit
            $rekeningAwal  = $sumberAkun->kode_rek;
            $rekeningAkhir = $tujuanAkun->kode_rek;
            $nilaiSumber = $request->sumber_jumlah * $request->sumber_harga_satuan;
            $nilaiTujuan = $tujuanJumlah * $tujuanHarga;
            $nilaiPerRincian = [$sumber->id => $nilaiSumber];
            if ($tujuan) $nilaiPerRincian[$tujuan->id] = $nilaiTujuan;
            $this->pastikanSisaPaguBelanja($sumber, $nilaiPerRincian,
                $tujuan ? [] : [$rekeningAkhir => $nilaiTujuan], 'sumber_jumlah');
    
            // Semua perubahan awalnya diverifikasi Kepala Puskesmas dulu
            $verifikator = 'kepala puskesmas';
    
            // Update sumber
            $sumber->update([
                'uraian_perubahan'       => $request->sumber_uraian,
                'jumlah_perubahan'       => $request->sumber_jumlah,
                'satuan_perubahan'       => $request->sumber_satuan,
                'harga_satuan_perubahan' => $request->sumber_harga_satuan,
                'jumlah_harga_perubahan' => $nilaiSumber,
                'keterangan_perubahan'   => $sumber->keterangan_perubahan ?? $sumber->keterangan,
                'status_perubahan'       => 'pending',
                'verifikator_perubahan'  => $verifikator,
                'rekening_awal'          => $rekeningAwal,
                'rekening_akhir'         => $rekeningAkhir,
            ]);
    
            // Buat tujuan — jika tipe_tujuan existing, update record tersebut
            if ($tujuan) {
                $tujuan->update([
                    'uraian_perubahan' => $tujuanUraian,
                    'jumlah_perubahan' => $tujuanJumlah,
                    'satuan_perubahan' => $tujuanSatuan,
                    'harga_satuan_perubahan' => $tujuanHarga,
                    'jumlah_harga_perubahan' => $nilaiTujuan,
                    'keterangan_perubahan' => $tujuanKeterangan,
                    'status_perubahan' => 'pending',
                    'verifikator_perubahan' => $verifikator,
                    'rekening_awal'         => $rekeningAwal,
                    'rekening_akhir'        => $rekeningAkhir,
                    // Jangan set is_hasil_perubahan karena record sudah ada sebelumnya
                ]);
            } else {
                RencanaKebutuhan::create([
                    'nama_blud' => $sumber->nama_blud,
                    'nama_unit' => $sumber->nama_unit,
                    'tahun_anggaran' => $sumber->tahun_anggaran,
                    'program' => $sumber->program,
                    'kegiatan' => $sumber->kegiatan,
                    'subkegiatan' => $sumber->subkegiatan,
                    'ruang' => $sumber->ruang,
                    'sumber_dana' => $sumber->sumber_dana,
                    'akun_id' => $akunId,
                    'spesifikasi' => $sumber->spesifikasi,
                    'status' => $sumber->status,
                    'dokumen' => $sumber->dokumen,
                    // Murni diset 0 (item baru, belum ada anggaran awal)
                    'uraian' => $tujuanUraian,
                    'jumlah' => 0,
                    'satuan' => $tujuanSatuan,
                    'harga_satuan' => 0,
                    'jumlah_harga' => 0,
                    'keterangan' => $tujuanKeterangan,
                    // Perubahan diisi
                    'uraian_perubahan' => $tujuanUraian,
                    'jumlah_perubahan' => $tujuanJumlah,
                    'satuan_perubahan' => $tujuanSatuan,
                    'harga_satuan_perubahan' => $tujuanHarga,
                    'jumlah_harga_perubahan' => $nilaiTujuan,
                    'keterangan_perubahan' => $tujuanKeterangan,
                    'status_perubahan' => 'pending',
                    'verifikator_perubahan' => $verifikator,
                    'rekening_awal'         => $rekeningAwal,
                    'rekening_akhir'        => $rekeningAkhir,
                    'is_hasil_perubahan'    => true,
                ]);
            }
        });

        return redirect()->route('rba-perubahan.belanja')
            ->with('success', 'Pengajuan pergeseran lintas kode rekening berhasil dibuat dan menunggu verifikasi.');
    }

    public function tolakBelanjaPerubahan($id)
    {
        $renbut = RencanaKebutuhan::where('tahun_anggaran', \App\Support\TahunRba::selected())->findOrFail($id);
        
        $renbut->update([
            'uraian_perubahan'       => null,
            'jumlah_perubahan'       => null,
            'satuan_perubahan'       => null,
            'harga_satuan_perubahan' => null,
            'jumlah_harga_perubahan' => null,
            'keterangan_perubahan'   => null,
            'status_perubahan'       => null,
            'verifikator_perubahan'  => null,
            'rekening_awal'          => null,
            'rekening_akhir'         => null,
        ]);

        return redirect()->route('rba-perubahan.belanja')
            ->with('success', 'Perubahan Rencana Belanja berhasil ditolak.');
    }

    public function pendapatan()
    {
        $klasifikasi = [
            'jasa_layanan'                      => ['prefix' => '4.1', 'label' => '4.1 - JASA LAYANAN',                      'color' => 'table-primary'],
            'hibah'                             => ['prefix' => '4.2', 'label' => '4.2 - HIBAH',                             'color' => 'table-info'],
            'hasil_kerjasama'                   => ['prefix' => '4.3', 'label' => '4.3 - HASIL KERJASAMA',                   'color' => 'table-success'],
            'apbd'                              => ['prefix' => '4.4', 'label' => '4.4 - APBD',                              'color' => 'table-success'],
            'lain_lain_pendapatan_blud_yang_sah'=> ['prefix' => '4.5', 'label' => 'LAIN-LAIN PENDAPATAN BLUD YANG SAH',      'color' => 'table-success'],
        ];
        
        return view('rba-pendapatan', $this->getPendapatanData($klasifikasi));
    }

    public function pendapatanPerubahan()
    {
        $klasifikasi = [
            'jasa_layanan'                      => ['prefix' => '4.1', 'label' => '4.1 - JASA LAYANAN',                      'color' => 'table-primary'],
            'hibah'                             => ['prefix' => '4.2', 'label' => '4.2 - HIBAH',                             'color' => 'table-info'],
            'hasil_kerjasama'                   => ['prefix' => '4.3', 'label' => '4.3 - HASIL KERJASAMA',                   'color' => 'table-success'],
            'apbd'                              => ['prefix' => '4.4', 'label' => '4.4 - APBD',                              'color' => 'table-success'],
            'lain_lain_pendapatan_blud_yang_sah'=> ['prefix' => '4.5', 'label' => 'LAIN-LAIN PENDAPATAN BLUD YANG SAH',      'color' => 'table-success'],
        ];
        
        return view('rba-pendapatan-perubahan', $this->getPendapatanData($klasifikasi, true));
    }

    public function pembiayaan()
    {
        $klasifikasi = [
            'penerimaan_pembiayaan'  => ['prefix' => '6.1', 'label' => '6.1 - PENERIMAAN PEMBIAYAAN',  'color' => 'table-primary'],
            'pengeluaran_pembiayaan' => ['prefix' => '6.2', 'label' => '6.2 - PENGELUARAN PEMBIAYAAN', 'color' => 'table-info'],
        ];
        return view('rba-pembiayaan', $this->getPembiayaanData($klasifikasi));
    }

    public function pembiayaanPerubahan()
    {
        $klasifikasi = [
            'penerimaan_pembiayaan'  => ['prefix' => '6.1', 'label' => '6.1 - PENERIMAAN PEMBIAYAAN',  'color' => 'table-primary'],
            'pengeluaran_pembiayaan' => ['prefix' => '6.2', 'label' => '6.2 - PENGELUARAN PEMBIAYAAN', 'color' => 'table-info'],
        ];
        return view('rba-pembiayaan-perubahan', $this->getPembiayaanData($klasifikasi, true));
    }
    
    public function updatePembiayaanPerubahan(Request $request, $id)
    {
        $pembiayaan = $this->pembiayaanQuery()->findOrFail($id);
        abort_if($pembiayaan->status_perubahan === 'verified', 403, 'Perubahan yang sudah diverifikasi tidak dapat diubah.');
        $data = app(PembiayaanValidator::class)->validate($request);
        $changes = ['status_perubahan' => 'pending'];
        foreach ($data as $field => $value) $changes[$field.'_perubahan'] = $value;
        $pembiayaan->update($changes);

        return redirect()->route('rba-perubahan.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Perubahan Rencana Pembiayaan berhasil disimpan');
    }

    public function zeroOutPembiayaanPerubahan($id)
    {
        $pembiayaan = $this->pembiayaanQuery()->findOrFail($id);
        abort_if($pembiayaan->status_perubahan === 'verified', 403, 'Perubahan yang sudah diverifikasi tidak dapat diubah.');
        $data = $pembiayaan->rincian(true);
        $data['volume_1'] = 0;
        $data['jumlah'] = '0.00';
        $changes = ['status_perubahan' => 'pending'];
        foreach ($data as $field => $value) $changes[$field.'_perubahan'] = $value;
        $pembiayaan->update($changes);

        return redirect()->route('rba-perubahan.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Perubahan Rencana Pembiayaan berhasil dihapus (nilai perubahan diubah menjadi 0 dan berstatus pending).');
    }

    public function verifikasiPembiayaanPerubahan($id)
    {
        $this->ensurePembiayaanReviewer();
        $pembiayaan = $this->pembiayaanQuery(true)->where('status_perubahan', 'pending')->findOrFail($id);

        $pembiayaan->update([
            'status_perubahan' => 'verified',
        ]);

        return redirect()->route('rba-perubahan.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Perubahan Rencana Pembiayaan berhasil diverifikasi.');
    }

    public function tolakPembiayaanPerubahan($id)
    {
        $this->ensurePembiayaanReviewer();
        $pembiayaan = $this->pembiayaanQuery(true)->where('status_perubahan', 'pending')->findOrFail($id);

        $pembiayaan->update([
            'status_perubahan' => 'rejected',
        ]);

        return redirect()->route('rba-perubahan.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Perubahan Rencana Pembiayaan berhasil ditolak.');
    }

    public function verifikasiSemuaPembiayaanPerubahan()
    {
        $this->ensurePembiayaanReviewer();
        $query = $this->pembiayaanQuery(true)->where('status_perubahan', 'pending');

        if (request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        $query->update(['status_perubahan' => 'verified']);

        return redirect()->route('rba-perubahan.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Semua Perubahan Rencana Pembiayaan berhasil diverifikasi.');
    }

    public function tolakSemuaPembiayaanPerubahan()
    {
        $this->ensurePembiayaanReviewer();
        $query = $this->pembiayaanQuery(true)->where('status_perubahan', 'pending');

        if (request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        $query->update(['status_perubahan' => 'rejected']);

        return redirect()->route('rba-perubahan.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Semua Perubahan Rencana Pembiayaan berhasil ditolak.');
    }
    
    public function storePembiayaan(Request $request)
    {
        $tahunRba = TahunRba::selected($request);
        $validated = app(PembiayaanValidator::class)->validate($request);

        $user = auth()->user();

        abort_if(empty($user->upt), 422, 'Unit BLUD pengguna belum ditentukan.');
        \App\Models\RencanaPembiayaan::create($validated + [
            'tahun_anggaran' => $tahunRba,
            'user_id' => $user->id,
            'nama_blud' => $user->upt,
        ]);

        return redirect()->route('rba.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Rencana Pembiayaan berhasil ditambahkan.');
    }

    public function updatePembiayaan(Request $request, $id)
    {
        $pembiayaan = $this->pembiayaanQuery()->findOrFail($id);
        $pembiayaan->update(app(PembiayaanValidator::class)->validate($request));

        return redirect()->route('rba.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Rencana Pembiayaan berhasil diperbarui.');
    }

    public function destroyPembiayaan($id)
    {
        $pembiayaan = $this->pembiayaanQuery()->findOrFail($id);
        $pembiayaan->delete();

        return redirect()->route('rba.pembiayaan', $this->pembiayaanFilters())
            ->with('success', 'Rencana Pembiayaan berhasil dihapus.');
    }

    private function ensurePembiayaanReviewer(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['superuser', 'dinas', 'dinas kesehatan']), 403);
    }

    private function pembiayaanQuery(bool $review = false)
    {
        $user = auth()->user();
        $canViewAll = $user->hasRole('Superuser')
            || ($review && $user->hasAnyRole(['dinas', 'dinas kesehatan']));

        return \App\Models\RencanaPembiayaan::query()->where('tahun_anggaran', TahunRba::selected())
            ->when(!$canViewAll, fn ($query) => $query->where('nama_blud', $user->upt));
    }

    private function pembiayaanFilters(): array
    {
        $filters = ['tahun' => TahunRba::selected()];
        if (auth()->user()->hasRole('Superuser') && request()->filled('puskesmas')) {
            $filters['puskesmas'] = request('puskesmas');
        }

        return $filters;
    }
    
    public function downloadPdf()
    {
        $user = auth()->user();

        $query = RencanaKebutuhan::with('akuns')->where('tahun_anggaran', TahunRba::selected());

        // selain Superuser, batasi berdasarkan BLUD user login
        if (!$this->isPrivilegedRole($user)) {
            $query->where('nama_blud', $user->upt)
                ->whereIn('nama_unit', $user->roleNames());
        } elseif (!$user->hasRole('Superuser')) {
            $query->where('nama_blud', $user->upt);
        }

        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        $renbut = $query->get()
            ->sortBy('akun_id', SORT_NATURAL | SORT_FLAG_CASE);

        $grouped = $renbut
            ->sortBy(fn ($item) => optional($item->akuns)->kode_rek, SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy(fn ($item) => optional($item->akuns)->kode_rek);

        $uptName = $user->upt;
        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }

        $penandatangan = UnitBlud::query()
            ->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])
            ->first();

        $tahun = TahunRba::selected();
        $kinerjaKegiatan = $penandatangan ? $penandatangan->kinerjaKegiatans()->where('tahun', $tahun)->first() : null;

        $pdf = Pdf::loadView('pdf.rba', compact('grouped', 'user', 'penandatangan', 'uptName', 'kinerjaKegiatan', 'tahun'))
            ->setPaper('A4', 'portrait');
            
        $filename = 'RBA-' . $uptName . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
    public function downloadBelanjaPerubahanPdf()
    {
        $user = auth()->user();
        
        $klasifikasi = [
            'belanja_pegawai'           => ['prefix' => '5.1.01', 'label' => '5.1.01 - BELANJA PEGAWAI BLUD',                     'color' => 'table-primary'],
            'belanja_barang_jasa'       => ['prefix' => '5.1.02', 'label' => '5.1.02 - BELANJA BARANG DAN JASA BLUD',             'color' => 'table-info'],
            'belanja_modal_peralatan'   => ['prefix' => '5.2.02', 'label' => '5.2.02 - BELANJA MODAL PERALATAN DAN MESIN BLUD',   'color' => 'table-warning'],
            'belanja_modal_gedung'      => ['prefix' => '5.2.03', 'label' => '5.2.03 - BELANJA MODAL GEDUNG DAN BANGUNAN BLUD',   'color' => 'table-success'],
            'belanja_modal_jalan'       => ['prefix' => '5.2.04', 'label' => '5.2.04 - BELANJA MODAL JALAN, JARINGAN DAN IRIGASI BLUD', 'color' => 'table-secondary'],
        ];

        $data = $this->getRbaData($klasifikasi, true, true);

        $uptName = $user->upt;
        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }

        $penandatangan = \App\Models\UnitBlud::query()
            ->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])
            ->first();

        $tahun = TahunRba::selected();
        $kinerjaKegiatan = $penandatangan ? $penandatangan->kinerjaKegiatans()->where('tahun', $tahun)->first() : null;

        $pdf = Pdf::loadView('pdf.rba-belanja-perubahan', array_merge($data, [
            'user' => $user,
            'uptName' => $uptName,
            'penandatangan' => $penandatangan,
            'kinerjaKegiatan' => $kinerjaKegiatan,
            'tahun' => $tahun,
        ]))->setPaper('legal', 'landscape');

        $filename = 'RBA-Perubahan-Belanja-' . $uptName . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadPendapatanPerubahanPdf()
    {
        $user = auth()->user();
        
        $klasifikasi = [
            'jasa_layanan' => ['prefix' => '4.1', 'label' => 'JASA LAYANAN', 'color' => 'table-primary'],
            'hibah'        => ['prefix' => '4.2', 'label' => 'HIBAH', 'color' => 'table-info'],
            'kerjasama'    => ['prefix' => '4.3', 'label' => 'HASIL KERJASAMA', 'color' => 'table-success'],
            'apbd'         => ['prefix' => '4.4', 'label' => 'APBD', 'color' => 'table-warning'],
            'lain_lain'    => ['prefix' => '4.5', 'label' => 'LAIN-LAIN PENDAPATAN BLUD YANG SAH', 'color' => 'table-secondary'],
        ];

        $data = $this->getPendapatanData($klasifikasi, true, true);

        $multiBlud = \App\Support\AlurPerubahanPendapatan::isSuperuser($user) || \App\Support\AlurPerubahanPendapatan::isDinas($user);
        $uptName = $multiBlud ? (request('puskesmas') ?: 'Semua Unit BLUD') : $user->upt;
        if ($multiBlud && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }

        $penandatangan = UnitBlud::query()
            ->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])
            ->first();

        // Pass everything to the view
        $pdf = Pdf::loadView('pdf.rba-pendapatan-perubahan', array_merge($data, [
            'user' => $user,
            'uptName' => $uptName,
            'penandatangan' => $penandatangan,
        ]))->setPaper('A4', 'portrait');

        $filename = 'RBA-Perubahan-Pendapatan-' . $uptName . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadPendapatanPdf()
    {
        $user = auth()->user();
        
        $klasifikasi = [
            'jasa_layanan' => ['prefix' => '4.1', 'label' => 'JASA LAYANAN', 'color' => 'table-primary'],
            'hibah'        => ['prefix' => '4.2', 'label' => 'HIBAH', 'color' => 'table-info'],
            'kerjasama'    => ['prefix' => '4.3', 'label' => 'HASIL KERJASAMA', 'color' => 'table-success'],
            'apbd'         => ['prefix' => '4.4', 'label' => 'APBD', 'color' => 'table-warning'],
            'lain_lain'    => ['prefix' => '4.5', 'label' => 'LAIN-LAIN PENDAPATAN BLUD YANG SAH', 'color' => 'table-secondary'],
        ];

        $data = $this->getPendapatanData($klasifikasi);

        $uptName = $user->upt;
        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }

        $penandatangan = UnitBlud::query()
            ->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])
            ->first();

        // Pass everything to the view
        $pdf = Pdf::loadView('pdf.rba-pendapatan', array_merge($data, [
            'user' => $user,
            'uptName' => $uptName,
            'penandatangan' => $penandatangan,
        ]))->setPaper('A4', 'portrait');

        $filename = 'RBA-Pendapatan-' . $uptName . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadPembiayaanPerubahanPdf()
    {
        $user = auth()->user();
        
        $klasifikasi = [
            'penerimaan_pembiayaan'  => ['prefix' => '6.1', 'label' => '6.1 - PENERIMAAN PEMBIAYAAN',  'color' => 'table-primary'],
            'pengeluaran_pembiayaan' => ['prefix' => '6.2', 'label' => '6.2 - PENGELUARAN PEMBIAYAAN', 'color' => 'table-info'],
        ];

        $data = $this->getPembiayaanData($klasifikasi, true, true);

        $uptName = $user->upt;
        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }

        $penandatangan = UnitBlud::query()
            ->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])
            ->first();

        $pdf = Pdf::loadView('pdf.rba-pembiayaan-perubahan', array_merge($data, [
            'user' => $user,
            'uptName' => $uptName,
            'penandatangan' => $penandatangan,
        ]))->setPaper('A4', 'portrait');

        $filename = 'RBA-Perubahan-Pembiayaan-' . $uptName . '-' . $data['tahunRba'] . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadPembiayaanPdf()
    {
        $user = auth()->user();
        
        $klasifikasi = [
            'penerimaan_pembiayaan'  => ['prefix' => '6.1', 'label' => '6.1 - PENERIMAAN PEMBIAYAAN',  'color' => 'table-primary'],
            'pengeluaran_pembiayaan' => ['prefix' => '6.2', 'label' => '6.2 - PENGELUARAN PEMBIAYAAN', 'color' => 'table-info'],
        ];

        $data = $this->getPembiayaanData($klasifikasi);

        $uptName = $user->upt;
        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }

        $penandatangan = UnitBlud::query()
            ->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])
            ->first();

        // Pass everything to the view
        $pdf = Pdf::loadView('pdf.rba-pembiayaan', array_merge($data, [
            'user' => $user,
            'uptName' => $uptName,
            'penandatangan' => $penandatangan,
        ]))->setPaper('A4', 'portrait');

        $filename = 'RBA-Pembiayaan-' . $uptName . '-' . $data['tahunRba'] . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    public function store(Request $request)
    {
        $request->validate([
            'rencana_kebutuhan_id' => 'required',
            'uraian' => 'required',
            'koefisien' => 'required',
            'satuan' => 'required',
            'harga' => 'required',
            'ppn' => 'required',
            'jumlah' => 'required',
        ]);

        RincianObjek::create($request->all());

        return redirect()->route('rincian-objek.index')
            ->with('success', 'Data Rincian Objek berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'rencana_kebutuhan_id' => 'required',
            'uraian' => 'required',
            'koefisien' => 'required',
            'satuan' => 'required',
            'harga' => 'required',
            'ppn' => 'required',
            'jumlah' => 'required',
        ]);

        $ro = RincianObjek::find($id);
        $ro->update($request->all());

        return redirect()->route('rincian-objek.index')
            ->with('success', 'Data Rincian Objek berhasil diperbarui');
    }

    public function destroy($id)
    {
        $ro = RincianObjek::find($id);
        $ro->delete();

        return redirect()->route('rincian-objek.index')
            ->with('success', 'Data Rincian Objek berhasil dihapus');
    }

    // public function search(Request $request)
    // {
    //     $search = $request->q;

    //     $data = Akun::where('nama','like',"%$search%")
    //             ->limit(20)
    //             ->get();

    //     return response()->json(
    //     $data->map(function($item){
    //         return [
    //             'id' => $item->id,
    //             'text' => $item->uraian
    //         ];
    //         })
    //     );
    // }

    public function ringkasan()
    {
        $klasifikasiPendapatan = [
            'pendapatan' => ['prefix' => '4', 'label' => '4 - PENDAPATAN', 'color' => 'table-primary']
        ];
        $klasifikasiBelanja = [
            'belanja' => ['prefix' => '5', 'label' => '5 - BELANJA', 'color' => 'table-info']
        ];
        $klasifikasiPembiayaan = [
            'pembiayaan' => ['prefix' => '6', 'label' => '6 - PEMBIAYAAN', 'color' => 'table-success']
        ];

        $pendapatan = $this->getPendapatanData($klasifikasiPendapatan, false, false);
        $belanja = $this->getRbaData($klasifikasiBelanja, false, false);
        $pembiayaan = $this->getPembiayaanData($klasifikasiPembiayaan, false, false);

        $tahun = TahunRba::selected();
        $puskesmasList = [];
        $user = auth()->user();
        if ($user->hasRole('Superuser')) {
            $puskesmasList = UnitBlud::orderBy('nama')->get();
        }

        return view('rba-ringkasan', compact('pendapatan', 'belanja', 'pembiayaan', 'tahun', 'puskesmasList'));
    }

    public function downloadRingkasanPdf()
    {
        $klasifikasiPendapatan = ['pendapatan' => ['prefix' => '4', 'label' => '4 - PENDAPATAN', 'color' => 'table-primary']];
        $klasifikasiBelanja = ['belanja' => ['prefix' => '5', 'label' => '5 - BELANJA', 'color' => 'table-info']];
        $klasifikasiPembiayaan = ['pembiayaan' => ['prefix' => '6', 'label' => '6 - PEMBIAYAAN', 'color' => 'table-success']];

        $pendapatan = $this->getPendapatanData($klasifikasiPendapatan, false, false);
        $belanja = $this->getRbaData($klasifikasiBelanja, false, false);
        $pembiayaan = $this->getPembiayaanData($klasifikasiPembiayaan, false, false);
        
        $user = auth()->user();
        $uptName = $user->upt;
        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }
        $penandatangan = \App\Models\UnitBlud::query()->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])->first();
        
        $tahun = TahunRba::selected();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.rba-ringkasan', compact('pendapatan', 'belanja', 'pembiayaan', 'user', 'uptName', 'penandatangan', 'tahun'))->setPaper('a4', 'portrait');
        return $pdf->stream('Ringkasan_RBA_'.$uptName.'_'.$tahun.'.pdf');
    }

    public function ringkasanPerubahan()
    {
        $klasifikasiPendapatan = [
            'pendapatan' => ['prefix' => '4', 'label' => '4 - PENDAPATAN', 'color' => 'table-primary']
        ];
        $klasifikasiBelanja = [
            'belanja' => ['prefix' => '5', 'label' => '5 - BELANJA', 'color' => 'table-info']
        ];
        $klasifikasiPembiayaan = [
            'pembiayaan' => ['prefix' => '6', 'label' => '6 - PEMBIAYAAN', 'color' => 'table-success']
        ];

        $pendapatan = $this->getPendapatanData($klasifikasiPendapatan, true, true);
        $belanja = $this->getRbaData($klasifikasiBelanja, true, true);
        $pembiayaan = $this->getPembiayaanData($klasifikasiPembiayaan, true, true);

        $tahun = TahunRba::selected();
        $puskesmasList = [];
        $user = auth()->user();
        if ($user->hasRole('Superuser')) {
            $puskesmasList = UnitBlud::orderBy('nama')->get();
        }

        return view('rba-ringkasan-perubahan', compact('pendapatan', 'belanja', 'pembiayaan', 'tahun', 'puskesmasList'));
    }

    public function downloadRingkasanPerubahanPdf()
    {
        $klasifikasiPendapatan = ['pendapatan' => ['prefix' => '4', 'label' => '4 - PENDAPATAN', 'color' => 'table-primary']];
        $klasifikasiBelanja = ['belanja' => ['prefix' => '5', 'label' => '5 - BELANJA', 'color' => 'table-info']];
        $klasifikasiPembiayaan = ['pembiayaan' => ['prefix' => '6', 'label' => '6 - PEMBIAYAAN', 'color' => 'table-success']];

        $pendapatan = $this->getPendapatanData($klasifikasiPendapatan, true, true);
        $belanja = $this->getRbaData($klasifikasiBelanja, true, true);
        $pembiayaan = $this->getPembiayaanData($klasifikasiPembiayaan, true, true);
        
        $user = auth()->user();
        $uptName = $user->upt;
        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $uptName = request('puskesmas');
        }
        $penandatangan = \App\Models\UnitBlud::query()->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])->first();
        
        $tahun = TahunRba::selected();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.rba-ringkasan-perubahan', compact('pendapatan', 'belanja', 'pembiayaan', 'user', 'uptName', 'penandatangan', 'tahun'))->setPaper('a4', 'portrait');
        return $pdf->stream('Ringkasan_RBA_Perubahan_'.$uptName.'_'.$tahun.'.pdf');
    }
}
