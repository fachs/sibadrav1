<?php

namespace App\Http\Controllers;

use App\Models\RekeningBlud;
use App\Models\UnitBlud;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RekeningBludController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureTableExists();

        $user = $request->user();
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
        if ($selectedUnit) {
            $rekeningByJenis = RekeningBlud::with('updater:id,name')
                ->where('unit_blud_id', $selectedUnit->id)
                ->get()
                ->keyBy('jenis');
        }

        $slots = collect(RekeningBlud::JENIS_LIST)->map(function (string $jenis) use ($rekeningByJenis) {
            return [
                'jenis' => $jenis,
                'record' => $rekeningByJenis->get($jenis),
            ];
        });

        $availableJenisForCreate = $slots
            ->filter(fn (array $slot) => $slot['record'] === null)
            ->pluck('jenis')
            ->values();

        $totalSaldo = (float) $rekeningByJenis->sum(fn ($item) => (float) $item->saldo);

        return view('rekening-blud', [
            'slots' => $slots,
            'jenisList' => RekeningBlud::JENIS_LIST,
            'availableJenisForCreate' => $availableJenisForCreate,
            'totalSaldo' => $totalSaldo,
            'canManage' => $canManage,
            'isGlobalViewer' => $isGlobalViewer && ! $canManage,
            'puskesmasList' => $puskesmasList,
            'selectedUnit' => $selectedUnit,
        ]);
    }

    public function store(Request $request)
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
            'saldo' => ['required', 'numeric', 'min:0'],
        ], [
            'jenis.unique' => 'Data rekening untuk jenis tersebut sudah terdaftar. Gunakan tombol Edit untuk memperbarui.',
            'jenis.in' => 'Jenis rekening tidak valid.',
        ]);

        RekeningBlud::create([
            'unit_blud_id' => $unit->id,
            'nama_blud' => $unit->nama,
            'jenis' => $validated['jenis'],
            'nama_bank' => trim($validated['nama_bank']),
            'nomor_rekening' => trim($validated['nomor_rekening']),
            'saldo' => $validated['saldo'],
            'updated_by' => $user->id,
        ]);

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
            'saldo' => ['required', 'numeric', 'min:0'],
        ], [
            'jenis.unique' => 'Jenis rekening tersebut sudah digunakan oleh rekening lain di unit ini.',
        ]);

        $rekening->update([
            'jenis' => $validated['jenis'],
            'nama_bank' => trim($validated['nama_bank']),
            'nomor_rekening' => trim($validated['nomor_rekening']),
            'saldo' => $validated['saldo'],
            'updated_by' => $user->id,
        ]);

        return redirect()->route('rekening-blud.index')
            ->with('success', 'Data '.$validated['jenis'].' berhasil diperbarui.');
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
        if (Schema::hasTable('rekening_bluds')) {
            return;
        }

        Schema::create('rekening_bluds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_blud_id')->constrained('unit_bluds')->cascadeOnDelete();
            $table->string('nama_blud');
            $table->string('jenis', 100);
            $table->string('nama_bank', 150);
            $table->string('nomor_rekening', 100);
            $table->decimal('saldo', 18, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_blud_id', 'jenis']);
        });
    }
}
