<?php

namespace App\Http\Controllers;

use App\Models\BkuBendaharaPengeluaran;
use App\Models\BkuBendaharaPerubahanRequest;
use App\Models\SpdLs;
use App\Support\TahunAnggaran;
use App\Support\UnitBludPengguna;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BkuBendaharaPerubahanRequestController extends Controller
{
    use UnitBludPengguna;

    public function index()
    {
        $verifikator = $this->verifikatorPengguna();
        $unitId = $verifikator === BkuBendaharaPerubahanRequest::KEPALA_PUSKESMAS
            ? $this->wajibUnitBludPengguna() : null;

        $pengajuan = BkuBendaharaPerubahanRequest::with(['bku', 'spdLs', 'unitBlud'])
            ->where(function ($query) {
                $query->whereHas('bku', fn ($bku) => TahunAnggaran::dates($bku))
                    ->orWhereHas('spdLs', fn ($spdLs) => $spdLs->where('tahun_anggaran', TahunAnggaran::selected()));
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()->get()
            ->filter(fn ($item) => ($item->status === 'pending' ? $item->verifikatorSaatIni() : $item->verifikator) === $verifikator
                && ($unitId === null || (int) $item->unit_blud_id === $unitId));

        return view('bku-bendahara-pengajuan', compact('pengajuan', 'verifikator'));
    }

    public function store(Request $request, BkuBendaharaPengeluaran $bku)
    {
        abort_unless($request->user()->hasRole('Bendahara Pengeluaran'), 403);
        $unitId = $this->wajibUnitBludPengguna();
        abort_unless((int) $bku->unit_blud_id === $unitId && !$bku->is_saldo_awal && $bku->parent_bku_id === null
            && Carbon::parse($bku->tanggal)->year === TahunAnggaran::selected(), 404);

        $validated = $request->validate([
            'aksi' => ['required', Rule::in(['edit', 'hapus'])],
            'alasan' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        abort_if(Carbon::parse($bku->tanggal)->toDateString() === now('Asia/Jakarta')->toDateString(), 422,
            'Transaksi hari ini dapat langsung diubah tanpa pengajuan.');
        $verifikator = BkuBendaharaPerubahanRequest::verifikatorUntukTanggal($bku->tanggal);

        DB::transaction(function () use ($request, $bku, $unitId, $validated, $verifikator) {
            $active = BkuBendaharaPerubahanRequest::where('unit_blud_id', $unitId)
                ->where('bku_id', $bku->id)->where('aksi', $validated['aksi'])
                ->where(fn ($query) => $query->where('status', 'pending')
                    ->orWhere(fn ($approved) => $approved->where('status', 'approved')->where('verifikator', $verifikator)))
                ->lockForUpdate()->exists();
            if ($active) {
                throw ValidationException::withMessages(['aksi' => 'Pengajuan untuk aksi ini masih menunggu atau sudah disetujui.']);
            }
            BkuBendaharaPerubahanRequest::create([
                'bku_id' => $bku->id, 'unit_blud_id' => $unitId,
                'aksi' => $validated['aksi'], 'status' => 'pending', 'verifikator' => $verifikator,
                'alasan' => $validated['alasan'], 'requested_by' => $request->user()->getAuthIdentifier(),
            ]);
        });

        $tujuan = $verifikator === BkuBendaharaPerubahanRequest::KEPALA_PUSKESMAS ? 'Kepala Puskesmas' : 'Dinas Kesehatan';
        return redirect()->route('bku-bendahara-pengeluaran.index')->with('success', 'Pengajuan dikirim ke '.$tujuan.'.');
    }

    public function review(Request $request, BkuBendaharaPerubahanRequest $pengajuan)
    {
        $verifikator = $this->verifikatorPengguna();
        $unitId = $verifikator === BkuBendaharaPerubahanRequest::KEPALA_PUSKESMAS
            ? $this->wajibUnitBludPengguna() : null;
        $validated = $request->validate(['keputusan' => ['required', Rule::in(['approved', 'rejected'])]]);

        DB::transaction(function () use ($request, $pengajuan, $validated, $verifikator, $unitId) {
            $pengajuan = BkuBendaharaPerubahanRequest::with('bku')->whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();
            abort_unless($pengajuan->status === 'pending', 409, 'Pengajuan sudah diproses.');
            abort_unless($pengajuan->verifikatorSaatIni() === $verifikator
                && ($unitId === null || (int) $pengajuan->unit_blud_id === $unitId), 403);
            if ($pengajuan->aksi === 'rincian') {
                $spdLs = SpdLs::whereKey($pengajuan->spd_ls_id)
                    ->where('unit_blud_id', $pengajuan->unit_blud_id)->first();
                abort_unless($spdLs, 409, 'SPD LS tidak tersedia.');
            } else {
                $bku = BkuBendaharaPengeluaran::whereKey($pengajuan->bku_id)
                    ->where('unit_blud_id', $pengajuan->unit_blud_id)->first();
                abort_unless($bku && !$bku->is_saldo_awal && $bku->parent_bku_id === null, 409, 'Transaksi BKU tidak tersedia.');
            }
            $pengajuan->update([
                'status' => $validated['keputusan'],
                'verifikator' => $verifikator,
                'reviewed_by' => $request->user()->getAuthIdentifier(),
                'reviewed_at' => now('Asia/Jakarta'),
            ]);
        });

        return back()->with('success', $validated['keputusan'] === 'approved' ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.');
    }

    private function verifikatorPengguna(): string
    {
        if (auth()->user()->isDinasKesehatan()) {
            return BkuBendaharaPerubahanRequest::DINAS_KESEHATAN;
        }

        abort_unless(auth()->user()->hasRole('Kepala Puskesmas'), 403);
        return BkuBendaharaPerubahanRequest::KEPALA_PUSKESMAS;
    }
}
