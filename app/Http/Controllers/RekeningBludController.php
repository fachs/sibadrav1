<?php

namespace App\Http\Controllers;

use App\Models\RekeningBlud;
use App\Models\UnitBlud;
use App\Models\User;
use App\Support\SaldoRekeningBludCalculator;
use App\Support\TahunAnggaran;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RekeningBludController extends Controller
{
    public function index(Request $request, SaldoRekeningBludCalculator $calculator)
    {
        $this->ensureTableExists();

        $user = $request->user();
        $tahun = TahunAnggaran::selected();
        $canManage = $this->canManageRekening($user);
        $isGlobalViewer = $user->hasAnyRole(['Superuser', 'Dinas', 'Dinas Kesehatan', 'Perencanaan']) || $user->isDinasKesehatan();

        $puskesmasList = collect();
        $selectedUnit = null;

        if ($isGlobalViewer && ! $canManage) {
            $puskesmasList = UnitBlud::orderBy('nama')->get();
            if ($request->filled('puskesmas')) {
                $selectedUnit = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $request->query('puskesmas')))])->first();
            } else {
                $selectedUnit = $puskesmasList->first();
            }
        } else {
            $selectedUnit = $this->userUnitBlud($user);
        }

        $rekeningByJenis = collect();
        $mutations = [];
        if ($selectedUnit) {
            $rekeningByJenis = RekeningBlud::with('updater:id,name')
                ->where('unit_blud_id', $selectedUnit->id)
                ->get()
                ->keyBy('jenis');

            $mutations = $calculator->mutationsForUnit($selectedUnit->id, $tahun);
        }

        $totalSaldo = 0.0;
        $totalSaldoAwal = 0.0;

        $slots = collect(RekeningBlud::JENIS_LIST)->map(function (string $jenis) use ($rekeningByJenis, $mutations, $calculator, &$totalSaldo, &$totalSaldoAwal) {
            /** @var RekeningBlud|null $record */
            $record = $rekeningByJenis->get($jenis);
            $mutation = $mutations[$jenis] ?? ['masuk' => 0.0, 'keluar' => 0.0];
            $saldoAwal = $record ? (float) $record->saldo_awal : 0.0;
            $saldoAkhir = $record ? $calculator->syncRekeningSaldo($record, $mutation) : 0.0;

            if ($record) {
                $totalSaldoAwal += $saldoAwal;
                $totalSaldo += $saldoAkhir;
            }

            return [
                'jenis' => $jenis,
                'record' => $record,
                'saldo_awal' => $saldoAwal,
                'masuk' => (float) $mutation['masuk'],
                'keluar' => (float) $mutation['keluar'],
                'saldo_akhir' => $saldoAkhir,
            ];
        });

        $availableJenisForCreate = $slots
            ->filter(fn (array $slot) => $slot['record'] === null)
            ->pluck('jenis')
            ->values();

        return view('rekening-blud', [
            'slots' => $slots,
            'jenisList' => RekeningBlud::JENIS_LIST,
            'availableJenisForCreate' => $availableJenisForCreate,
            'totalSaldoAwal' => $totalSaldoAwal,
            'totalSaldo' => $totalSaldo,
            'tahunAnggaran' => $tahun,
            'canManage' => $canManage,
            'isGlobalViewer' => $isGlobalViewer && ! $canManage,
            'puskesmasList' => $puskesmasList,
            'selectedUnit' => $selectedUnit,
        ]);
    }

    public function store(Request $request, SaldoRekeningBludCalculator $calculator)
    {
        $this->ensureTableExists();
        $user = $request->user();
        $this->authorizeManage($user);

        $unit = $this->userUnitBlud($user);
        abort_if($unit === null, 422, 'Unit BLUD pengguna belum terdaftar di Data UPTD.');

        $validated = $request->validate([
            'jenis' => [
                'required',
                'string',
                Rule::in(RekeningBlud::JENIS_LIST),
                Rule::unique('rekening_bluds', 'jenis')->where(fn ($q) => $q->where('unit_blud_id', $unit->id)),
            ],
            'nama_bank' => ['required', 'string', 'max:150'],
            'nomor_rekening' => ['required', 'string', 'max:100'],
            'saldo_awal' => ['nullable', 'numeric', 'min:0'],
        ], [
            'jenis.unique' => 'Data rekening untuk jenis tersebut sudah terdaftar. Gunakan tombol Edit untuk memperbarui info bank/nomor rekening.',
            'jenis.in' => 'Jenis rekening tidak valid.',
        ]);

        $saldoAwal = isset($validated['saldo_awal']) && $validated['saldo_awal'] !== '' && $validated['saldo_awal'] !== null
            ? (float) $validated['saldo_awal']
            : 0.0;

        $rekening = RekeningBlud::create([
            'unit_blud_id' => $unit->id,
            'nama_blud' => $unit->nama,
            'jenis' => $validated['jenis'],
            'nama_bank' => trim($validated['nama_bank']),
            'nomor_rekening' => trim($validated['nomor_rekening']),
            'saldo_awal' => $saldoAwal,
            'saldo' => $saldoAwal,
            'updated_by' => $user->id,
        ]);

        $mutations = $calculator->mutationsForUnit($unit->id, TahunAnggaran::selected());
        $calculator->syncRekeningSaldo($rekening, $mutations[$rekening->jenis] ?? ['masuk' => 0.0, 'keluar' => 0.0]);

        return redirect()->route('rekening-blud.index')
            ->with('success', 'Data '.$validated['jenis'].' berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $this->ensureTableExists();
        $user = $request->user();
        $this->authorizeManage($user);

        $unit = $this->userUnitBlud($user);
        abort_if($unit === null, 422, 'Unit BLUD pengguna belum terdaftar di Data UPTD.');

        $rekening = RekeningBlud::where('unit_blud_id', $unit->id)->findOrFail($id);

        // Saldo / saldo_awal sengaja tidak dapat diubah melalui fitur Edit Rekening.
        $validated = $request->validate([
            'jenis' => [
                'required',
                'string',
                Rule::in(RekeningBlud::JENIS_LIST),
                Rule::unique('rekening_bluds', 'jenis')
                    ->where(fn ($q) => $q->where('unit_blud_id', $unit->id))
                    ->ignore($rekening->id),
            ],
            'nama_bank' => ['required', 'string', 'max:150'],
            'nomor_rekening' => ['required', 'string', 'max:100'],
        ], [
            'jenis.unique' => 'Jenis rekening tersebut sudah digunakan oleh rekening lain di unit ini.',
        ]);

        $rekening->update([
            'jenis' => $validated['jenis'],
            'nama_bank' => trim($validated['nama_bank']),
            'nomor_rekening' => trim($validated['nomor_rekening']),
            'updated_by' => $user->id,
        ]);

        return redirect()->route('rekening-blud.index')
            ->with('success', 'Informasi bank & nomor '.$validated['jenis'].' berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureTableExists();
        $user = $request->user();
        $this->authorizeManage($user);

        $unit = $this->userUnitBlud($user);
        abort_if($unit === null, 422, 'Unit BLUD pengguna belum terdaftar di Data UPTD.');

        $rekening = RekeningBlud::where('unit_blud_id', $unit->id)->findOrFail($id);
        $jenis = $rekening->jenis;
        $rekening->delete();

        return redirect()->route('rekening-blud.index')
            ->with('success', 'Data '.$jenis.' berhasil dihapus.');
    }

    private function canManageRekening(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['Kepala Puskesmas', 'Pejabat Keuangan']);
    }

    private function authorizeManage(?User $user): void
    {
        abort_unless(
            $this->canManageRekening($user),
            403,
            'Akses ditolak. Hanya Kepala Puskesmas dan Pejabat Keuangan yang dapat menambahkan atau mengubah data rekening BLUD.'
        );
    }

    private function userUnitBlud(User $user): ?UnitBlud
    {
        $upt = trim((string) $user->upt);
        if ($upt === '') {
            return null;
        }

        return UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower($upt)])->first();
    }

    private function ensureTableExists(): void
    {
        if (! Schema::hasTable('rekening_bluds')) {
            Schema::create('rekening_bluds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnDelete();
                $table->string('nama_blud');
                $table->string('jenis', 100);
                $table->string('nama_bank', 150);
                $table->string('nomor_rekening', 100);
                $table->decimal('saldo_awal', 18, 2)->default(0);
                $table->decimal('saldo', 18, 2)->default(0);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['unit_blud_id', 'jenis']);
            });

            return;
        }

        if (! Schema::hasColumn('rekening_bluds', 'saldo_awal')) {
            Schema::table('rekening_bluds', function (Blueprint $table) {
                $table->decimal('saldo_awal', 18, 2)->default(0)->after('nomor_rekening');
            });

            DB::table('rekening_bluds')->update([
                'saldo_awal' => DB::raw('saldo'),
            ]);
        }
    }
}
