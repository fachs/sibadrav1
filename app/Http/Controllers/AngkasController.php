<?php

namespace App\Http\Controllers;

use App\Models\Angkas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AngkasController extends Controller
{
    use \App\Support\UnitBludPengguna;

    private const BULAN = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    public function index(Request $request)
    {
        $bulanList = self::BULAN;
        $angkas = $this->angkasQuery()->orderBy('jenis_belanja')->orderBy('id')->get();
        $jenisBelanjaList = $this->getJenisBelanjaList($angkas);
        $angkasMatrix = $this->buildAngkasMatrix($jenisBelanjaList, $angkas);
        
        $alokasiMatrix = [];
        foreach ($jenisBelanjaList as $jb) {
            $alokasiMatrix[$jb] = $angkas->where('jenis_belanja', $jb)->first()?->alokasi_anggaran ?? 0;
        }
        
        $totalAngkas = $angkas->sum('jumlah');
        $totalAlokasiAnggaran = array_sum($alokasiMatrix);
        $sisaAnggaran = max(0, $totalAlokasiAnggaran - $totalAngkas);

        if (!view()->exists('angkas')) {
            return response()->json($angkas);
        }

        return view('angkas', compact(
            'bulanList',
            'jenisBelanjaList',
            'angkasMatrix',
            'alokasiMatrix',
            'totalAngkas',
            'totalAlokasiAnggaran',
            'sisaAnggaran'
        ));
    }

    public function store(Request $request)
    {
        $unitId = $this->wajibUnitBludPengguna();
        if ($request->has('alokasi_anggaran')) {
            $request->merge([
                'alokasi_anggaran' => preg_replace('/[^0-9]/', '', $request->input('alokasi_anggaran'))
            ]);
        }

        $validated = $request->validate([
            'jenis_belanja' => ['required', 'string', 'max:255'],
            'alokasi_anggaran' => ['nullable', 'numeric', 'min:0'],
        ]);

        $alokasi = $validated['alokasi_anggaran'] ?? 0;

        foreach (self::BULAN as $bulan) {
            $this->angkasQuery()->firstOrCreate(
                [
                    'unit_blud_id' => $unitId,
                    'tahun_anggaran' => \App\Support\TahunAnggaran::selected(),
                    'jenis_belanja' => $validated['jenis_belanja'],
                    'bulan' => $bulan,
                ],
                [
                    'jumlah' => 0,
                    'alokasi_anggaran' => $alokasi,
                ]
            );
        }

        // Update alokasi_anggaran on the first row (the canonical one)
        $this->angkasQuery()->where('jenis_belanja', $validated['jenis_belanja'])
            ->update(['alokasi_anggaran' => $alokasi]);

        return redirect()->route('angkas.index')->with('success', 'Angkas berhasil ditambahkan.');
    }

    public function updateInline(Request $request)
    {
        $unitId = $this->wajibUnitBludPengguna();
        $validated = $request->validate([
            'jenis_belanja' => ['required', 'string', 'max:255'],
            'bulan' => ['required', 'string', Rule::in(self::BULAN)],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        $data = [
            'jenis_belanja' => $validated['jenis_belanja'],
            'bulan' => $validated['bulan'],
            'jumlah' => $validated['value'],
        ];

        $existing = $this->angkasQuery()->where('jenis_belanja', $data['jenis_belanja'])
            ->where('bulan', $data['bulan'])
            ->first();

        $this->ensureJumlahWithinAlokasiLimit($data, $existing?->id);

        $angkas = $this->angkasQuery()->updateOrCreate(
            [
                'unit_blud_id' => $unitId,
                'tahun_anggaran' => \App\Support\TahunAnggaran::selected(),
                'jenis_belanja' => $data['jenis_belanja'],
                'bulan' => $data['bulan'],
            ],
            [
                'jumlah' => $data['jumlah'],
            ]
        );

        return response()->json([
            'success' => true,
            'id' => $angkas->id,
            'jumlah' => $angkas->jumlah,
            'formatted' => 'Rp ' . number_format($angkas->jumlah, 0, ',', '.'),
        ]);
    }

    public function updateAlokasiInline(Request $request)
    {
        $this->wajibUnitBludPengguna();
        $validated = $request->validate([
            'jenis_belanja' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        $totalTerpakai = $this->angkasQuery()->where('jenis_belanja', $validated['jenis_belanja'])->sum('jumlah');

        if ($validated['value'] < $totalTerpakai) {
            return response()->json([
                'errors' => ['value' => ['Alokasi Anggaran tidak boleh lebih kecil dari total Angkas yang sudah terpakai (Rp ' . number_format($totalTerpakai, 0, ',', '.') . ').']]
            ], 422);
        }

        $this->angkasQuery()->where('jenis_belanja', $validated['jenis_belanja'])
            ->update(['alokasi_anggaran' => $validated['value']]);

        return response()->json([
            'success' => true,
            'alokasi_anggaran' => $validated['value'],
            'formatted' => 'Rp ' . number_format($validated['value'], 0, ',', '.'),
        ]);
    }

    public function updateModal(Request $request)
    {
        $unitId = $this->wajibUnitBludPengguna();
        $validated = $request->validate([
            'jenis_belanja' => ['required', 'string', 'max:255'],
            'alokasi_anggaran' => ['nullable', 'numeric', 'min:0'],
            'bulan' => ['required', 'array'],
            'bulan.*' => ['required', 'numeric', 'min:0'],
        ]);

        $jenisBelanja = $validated['jenis_belanja'];
        $bulanData = $validated['bulan'];
        $alokasiAnggaran = $validated['alokasi_anggaran'] ?? 0;

        $totalInput = array_sum($bulanData);

        if ($alokasiAnggaran > 0 && $totalInput > $alokasiAnggaran) {
            return response()->json([
                'errors' => ['total' => ['Total RAK (Rp ' . number_format($totalInput, 0, ',', '.') . ') melebihi Alokasi Anggaran (Rp ' . number_format($alokasiAnggaran, 0, ',', '.') . ').']],
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update alokasi_anggaran untuk semua row jenis belanja ini
            $this->angkasQuery()->where('jenis_belanja', $jenisBelanja)
                ->update(['alokasi_anggaran' => $alokasiAnggaran]);

            foreach (self::BULAN as $bulan) {
                $jumlah = $bulanData[$bulan] ?? 0;
                $this->angkasQuery()->updateOrCreate(
                    [
                        'unit_blud_id' => $unitId,
                        'tahun_anggaran' => \App\Support\TahunAnggaran::selected(),
                        'jenis_belanja' => $jenisBelanja,
                        'bulan' => $bulan,
                    ],
                    [
                        'jumlah' => $jumlah,
                        'alokasi_anggaran' => $alokasiAnggaran,
                    ]
                );
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Detail RAK berhasil disimpan.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['system' => ['Terjadi kesalahan saat menyimpan data.']]
            ], 500);

        }
    }

    public function update(Request $request, $id)
    {
        $angkas = $this->angkasQuery()->findOrFail($id);
        $validated = $this->validateRequest($request);
        $this->ensureJumlahWithinAlokasiLimit($validated, $angkas->id);

        $angkas->update($validated);

        return redirect()->route('angkas.index')->with('success', 'Angkas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $angkas = $this->angkasQuery()->findOrFail($id);
        $angkas->delete();

        return redirect()->route('angkas.index')->with('success', 'Angkas berhasil dihapus.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'jenis_belanja' => ['required', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'min:0'],
            'bulan' => ['required', 'string', Rule::in(self::BULAN)],
        ]);
    }

    private function ensureJumlahWithinAlokasiLimit(array $validated, ?int $ignoreId = null): void
    {
        $totalAlokasi = $this->angkasQuery()->where('jenis_belanja', $validated['jenis_belanja'])->first()?->alokasi_anggaran ?? 0;

        $totalAngkasTerpakai = $this->getTotalAngkasTerpakai(
            (string) $validated['jenis_belanja'],
            $ignoreId
        );

        $sisaBatas = max(0, $totalAlokasi - $totalAngkasTerpakai);

        if ($validated['jumlah'] > $sisaBatas) {
            throw ValidationException::withMessages([
                'jumlah' => 'Jumlah Angkas melebihi batas alokasi anggaran. Sisa yang dapat diinput: Rp ' . number_format($sisaBatas, 2, ',', '.'),
            ]);
        }
    }

    private function getTotalAngkasTerpakai(string $jenisBelanja, ?int $ignoreId = null): int
    {
        $query = $this->angkasQuery()
            ->where('jenis_belanja', $jenisBelanja);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->sum('jumlah');
    }

    private function getTotalRencanaKebutuhanForIndex(): int
    {
        if (!Schema::hasTable('rencana_kebutuhans') || !Schema::hasColumn('rencana_kebutuhans', 'jumlah_harga')) {
            return 0.0;
        }

        $query = DB::table('rencana_kebutuhans');
        $this->applyRencanaKebutuhanUserScope($query);

        return $query->sum('jumlah_harga');
    }

    private function getJenisBelanjaList($angkas)
    {
        $jenisBelanja = $angkas->pluck('jenis_belanja');

        if (Schema::hasTable('rencana_kebutuhans') && Schema::hasColumn('rencana_kebutuhans', 'jenis_belanja')) {
            $query = DB::table('rencana_kebutuhans')
                ->whereNotNull('jenis_belanja');
            $this->applyRencanaKebutuhanUserScope($query);

            $jenisBelanja = $jenisBelanja->merge(
                $query->pluck('jenis_belanja')
            );
        }

        return $jenisBelanja
            ->map(fn ($jenis) => trim((string) $jenis))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function buildAngkasMatrix($jenisBelanjaList, $angkas): array
    {
        $matrix = [];
        $grouped = $angkas->groupBy('jenis_belanja');

        foreach ($jenisBelanjaList as $jenisBelanja) {
            $items = $grouped->get($jenisBelanja, collect());

            foreach (self::BULAN as $bulan) {
                $item = $items->firstWhere('bulan', $bulan);
                $matrix[$jenisBelanja][$bulan] = $item ? $item->jumlah : 0.0;
            }
        }

        return $matrix;
    }

    private function buildRencanaKebutuhanMatrix($jenisBelanjaList): array
    {
        $matrix = [];

        foreach ($jenisBelanjaList as $jenisBelanja) {
            foreach (self::BULAN as $bulan) {
                $matrix[$jenisBelanja][$bulan] = 0.0;
            }
        }

        if (!Schema::hasTable('rencana_kebutuhans') || !Schema::hasColumn('rencana_kebutuhans', 'jumlah_harga')) {
            return $matrix;
        }

        $query = DB::table('rencana_kebutuhans')
            ->selectRaw('SUM(jumlah_harga) as total');
        $this->applyRencanaKebutuhanUserScope($query);

        if (Schema::hasColumn('rencana_kebutuhans', 'jenis_belanja')) {
            $query->addSelect('jenis_belanja')->groupBy('jenis_belanja');
        }

        if (Schema::hasColumn('rencana_kebutuhans', 'bulan')) {
            $query->addSelect('bulan')->groupBy('bulan');
        }

        foreach ($query->get() as $row) {
            $jenisBelanja = Schema::hasColumn('rencana_kebutuhans', 'jenis_belanja')
                ? trim((string) $row->jenis_belanja)
                : null;
            $bulan = Schema::hasColumn('rencana_kebutuhans', 'bulan')
                ? (string) $row->bulan
                : null;

            if ($jenisBelanja && $bulan && in_array($bulan, self::BULAN, true)) {
                $matrix[$jenisBelanja][$bulan] = $row->total;
            } elseif ($jenisBelanja) {
                foreach (self::BULAN as $bulanItem) {
                    $matrix[$jenisBelanja][$bulanItem] = $row->total;
                }
            }
        }

        return $matrix;
    }

    private function applyRencanaKebutuhanUserScope($query): void
    {
        $query->where('tahun_anggaran', \App\Support\TahunAnggaran::selected());
        if (!Schema::hasColumn('rencana_kebutuhans', 'nama_blud')) {
            return;
        }

        $userUpt = strtolower(trim((string) auth()->user()->upt));
        $query->whereRaw('LOWER(nama_blud) = ?', [$userUpt]);
    }

    private function angkasQuery()
    {
        return $this->batasiUnitBlud(Angkas::query())->where('tahun_anggaran', \App\Support\TahunAnggaran::selected());
    }
}
