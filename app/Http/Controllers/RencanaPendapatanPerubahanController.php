<?php

namespace App\Http\Controllers;

use App\Models\RencanaPendapatan;
use App\Models\UnitBlud;
use App\Support\AlurPerubahanPendapatan as Alur;
use App\Support\ReferensiPendapatan;
use App\Support\TahunRba;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\PendapatanPelayananValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RencanaPendapatanPerubahanController extends Controller
{
    private function isSuperuser(Request $request): bool
    {
        return Alur::isSuperuser($request->user());
    }

    private function query(Request $request)
    {
        return RencanaPendapatan::query()->where('tahun_anggaran', TahunRba::selected($request))->when(!$this->isSuperuser($request),
            fn ($query) => $query->where('nama_blud', $request->user()->upt));
    }

    private function ensureEditable(RencanaPendapatan $pendapatan, ?string $group = null): void
    {
        abort_if($pendapatan->status_perubahan === 'verified', 403, 'Usulan yang sudah diverifikasi tidak dapat diubah.');
        abort_if($pendapatan->pergeseran_id && $pendapatan->pergeseran_id !== $group, 403, 'Kelola kedua rekening melalui form Pengajuan Perubahan.');
    }

    private function redirect(Request $request, string $message, ?string $blud = null)
    {
        return redirect()->route('rba-perubahan.pendapatan',
            array_filter(['puskesmas' => ($this->isSuperuser($request) || Alur::isDinas($request->user())) ? $blud : null,
                'tahun' => $request->input('tahun')]))
            ->with('success', $message);
    }

    public function update(Request $request, int $id, PendapatanPelayananValidator $validator)
    {
        $blud = DB::transaction(function () use ($request, $id, $validator) {
            $pendapatan = $this->query($request)->lockForUpdate()->findOrFail($id);
            $this->ensureEditable($pendapatan);
            [$data, $blud, $total] = $validator->validate($request, $pendapatan, true);
            $pendapatan->update([
                'uraian_perubahan' => $data['uraian'], 'jumlah_perubahan' => $total,
                'jenis_pelayanan_perubahan' => $data['jenis_pelayanan'],
                'status_perubahan' => 'pending', 'verifikator_perubahan' => 'kepala puskesmas',
                'rekening_awal' => $pendapatan->kode_rek, 'rekening_akhir' => $pendapatan->kode_rek,
            ]);

            return $blud;
        });

        return $this->redirect($request, 'Usulan perubahan rincian pelayanan berhasil disimpan.', $blud);
    }

    public function zeroOut(Request $request, int $id)
    {
        abort_unless($request->user()->hasAnyRole([
            'superuser', 'kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes',
        ]), 403);
        $blud = DB::transaction(function () use ($request, $id) {
            $pendapatan = $this->query($request)->lockForUpdate()->findOrFail($id);
            $this->ensureEditable($pendapatan);
            $rows = $pendapatan->pelayananPerubahan()->map(function ($row) {
                return array_replace($row->toArray(), ['volume_1' => 0, 'jumlah' => '0.00']);
            })->values()->all();
            $pendapatan->update([
                'uraian_perubahan' => $pendapatan->uraian_perubahan ?? $pendapatan->uraian,
                'jumlah_perubahan' => '0.00', 'jenis_pelayanan_perubahan' => $rows,
                'status_perubahan' => 'pending', 'verifikator_perubahan' => 'kepala puskesmas',
                'rekening_awal' => $pendapatan->kode_rek, 'rekening_akhir' => $pendapatan->kode_rek,
            ]);

            return $pendapatan->nama_blud;
        });

        return $this->redirect($request, 'Usulan perubahan dinolkan dan menunggu verifikasi.', $blud);
    }

    private function reviewQuery(Request $request)
    {
        return Alur::isDinas($request->user()) ? RencanaPendapatan::query()->where('tahun_anggaran', TahunRba::selected($request)) : $this->query($request);
    }

    private function ensureReviewer(Request $request): void
    {
        abort_unless($this->isSuperuser($request) || Alur::isDinas($request->user())
            || $request->user()->hasRole('kepala puskesmas'), 403);
    }

    private function reviewBatch(Request $request, int $id, string $status): array
    {
        return DB::transaction(function () use ($request, $id, $status) {
            $record = $this->reviewQuery($request)->where('status_perubahan', 'pending')->findOrFail($id);
            $items = $this->reviewQuery($request)
                ->when($record->pergeseran_id,
                    fn ($query) => $query->where('pergeseran_id', $record->pergeseran_id),
                    fn ($query) => $query->whereKey($record->id))
                ->orderBy('id')->lockForUpdate()->get();
            abort_if($items->isEmpty(), 404);
            abort_if($record->pergeseran_id && $items->count() !== 2, 409, 'Pasangan rekening pergeseran tidak lengkap.');
            foreach ($items as $item) {
                abort_unless(Alur::canReview($request->user(), $item), 403, 'Usulan belum berada pada tahap verifikasi Anda.');
            }
            $forwarded = $status === 'verified' && !$this->isSuperuser($request)
                && Alur::lintasKlasifikasi($items->first()) && Alur::verifikator($items->first()) === 'kepala puskesmas';
            foreach ($items as $item) {
                if ($status === 'rejected') {
                    if ($item->is_hasil_perubahan && $item->pergeseran_id) {
                        $item->jenisPelayanans()->delete();
                        $item->delete();
                    } else {
                        $item->update([
                            'uraian_perubahan' => null, 'jumlah_perubahan' => null,
                            'jenis_pelayanan_perubahan' => null, 'status_perubahan' => null,
                            'verifikator_perubahan' => null, 'rekening_awal' => null,
                            'rekening_akhir' => null, 'pergeseran_id' => null,
                        ]);
                    }
                } else {
                    $item->update($forwarded
                        ? ['verifikator_perubahan' => 'dinas kesehatan']
                        : ['status_perubahan' => 'verified']);
                }
            }

            return [$record->nama_blud, $forwarded];
        }, 3);
    }

    private function review(Request $request, int $id, string $status)
    {
        $this->ensureReviewer($request);
        [$blud, $forwarded] = $this->reviewBatch($request, $id, $status);

        return $this->redirect($request, $status === 'rejected'
            ? 'Usulan ditolak. Rincian dikembalikan ke data awal.'
            : ($forwarded ? 'Disetujui Kepala Puskesmas dan diteruskan ke Dinas Kesehatan.' : 'Usulan berhasil diverifikasi.'), $blud);
    }

    public function verify(Request $request, int $id)
    {
        return $this->review($request, $id, 'verified');
    }

    public function reject(Request $request, int $id)
    {
        return $this->review($request, $id, 'rejected');
    }

    private function reviewAll(Request $request, string $status)
    {
        $this->ensureReviewer($request);
        $filter = $request->validate(['puskesmas' => ['nullable', 'string', 'max:255']])['puskesmas'] ?? null;
        DB::transaction(function () use ($request, $status, $filter) {
            $items = $this->reviewQuery($request)->where('status_perubahan', 'pending')
                ->when(($this->isSuperuser($request) || Alur::isDinas($request->user())) && $filter,
                    fn ($query) => $query->where('nama_blud', $filter))->orderBy('id')->get();
            $processed = [];
            foreach ($items as $item) {
                $key = $item->pergeseran_id ?: 'record-'.$item->id;
                if (isset($processed[$key]) || !Alur::canReview($request->user(), $item)) {
                    continue;
                }
                $processed[$key] = true;
                $this->reviewBatch($request, $item->id, $status);
            }
        }, 3);

        return $this->redirect($request, $status === 'verified'
            ? 'Usulan sesuai kewenangan berhasil diproses. Pergeseran lintas klasifikasi diteruskan ke Dinas Kesehatan.'
            : 'Usulan sesuai kewenangan berhasil ditolak dan dikembalikan ke data awal.', $filter);
    }

    public function verifyAll(Request $request)
    {
        return $this->reviewAll($request, 'verified');
    }

    public function rejectAll(Request $request)
    {
        return $this->reviewAll($request, 'rejected');
    }

    public function createShift(Request $request, ReferensiPendapatan $referensi)
    {
        $filters = $request->validate([
            'puskesmas' => ['nullable', 'string', 'max:255'],
            'edit_pergeseran' => ['nullable', 'uuid'],
        ]);
        $editing = null;
        if (!empty($filters['edit_pergeseran'])) {
            $pair = $this->query($request)->where('pergeseran_id', $filters['edit_pergeseran'])->with('jenisPelayanans')->get();
            abort_unless($pair->count() === 2, 404);
            foreach ($pair as $item) {
                $this->ensureEditable($item, $filters['edit_pergeseran']);
            }
            $source = $pair->first(fn ($item) => $item->kode_rek === $item->rekening_awal);
            $destination = $pair->first(fn ($item) => $item->kode_rek === $item->rekening_akhir);
            abort_unless($source && $destination, 409);
            $editing = ['pergeseran_id' => $source->pergeseran_id, 'sumber_id' => $source->id, 'tujuan_id' => $destination->id];
        }
        $records = $this->query($request)->with('jenisPelayanans')
            ->where(fn ($query) => $query->whereNull('status_perubahan')->orWhere('status_perubahan', '!=', 'verified'))
            ->where(fn ($query) => $query->whereNull('pergeseran_id')
                ->when($editing, fn ($query) => $query->orWhere('pergeseran_id', $editing['pergeseran_id'])))
            ->when($this->isSuperuser($request) && !empty($filters['puskesmas']), fn ($query) => $query->where('nama_blud', $filters['puskesmas']))
            ->orderBy('nama_blud')->orderBy('kode_rek')->get();
        $editorRecords = $records->map(fn ($record) => $record->dataEditorPerubahan() + [
            'jumlah_awal' => $record->jumlah_perubahan ?? $record->jumlah,
        ])->values();
        $isSuperuser = $this->isSuperuser($request);
        $puskesmasList = $isSuperuser ? UnitBlud::orderBy('nama')->get() : collect();

        return view('rba-pendapatan-perubahan-form', compact('editorRecords', 'records', 'isSuperuser', 'puskesmasList', 'editing'));
    }

    private function validateShiftRows(Request $request, PendapatanPelayananValidator $validator, RencanaPendapatan $record, string $field): array
    {
        $input = new Request(['kode_rek' => $record->kode_rek, 'jenis_pelayanan' => json_decode($request->input($field), true)]);
        $input->setUserResolver(fn () => $request->user());
        try {
            return $validator->validate($input, $record, true, true);
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $key => $errors) {
                $messages[$field.'.'.$key] = array_map(fn ($message) => ($field === 'sumber_rincian' ? 'Sumber: ' : 'Tujuan: ').$message, $errors);
            }
            throw ValidationException::withMessages($messages);
        }
    }

    public function storeShift(Request $request, PendapatanPelayananValidator $validator, ReferensiPendapatan $referensi)
    {
        $data = $request->validate([
            'sumber_pendapatan_id' => ['required', 'integer', 'min:1'],
            'tipe_tujuan' => ['required', Rule::in(['existing', 'baru'])],
            'tujuan_pendapatan_id' => ['required_if:tipe_tujuan,existing', 'nullable', 'integer', 'min:1'],
            'tujuan_kode_rek' => ['required_if:tipe_tujuan,baru', 'nullable', 'string', Rule::in($referensi->codes())],
            'sumber_rincian' => ['required', 'string', 'json', 'max:300000'],
            'tujuan_rincian' => ['required', 'string', 'json', 'max:300000'],
            'pergeseran_id' => ['nullable', 'uuid'],
        ]);
        try {
            $blud = DB::transaction(function () use ($request, $data, $validator, $referensi) {
                $group = $data['pergeseran_id'] ?? null;
                $source = $this->query($request)->lockForUpdate()->findOrFail($data['sumber_pendapatan_id']);
                $this->ensureEditable($source, $group);
                if ($group) {
                    abort_unless($source->pergeseran_id === $group && $source->kode_rek === $source->rekening_awal, 403);
                }
                if ($data['tipe_tujuan'] === 'existing') {
                    $destination = $this->query($request)->where('nama_blud', $source->nama_blud)->lockForUpdate()->findOrFail($data['tujuan_pendapatan_id']);
                    $this->ensureEditable($destination, $group);
                    if ($group) {
                        abort_unless($destination->pergeseran_id === $group && $destination->kode_rek === $source->rekening_akhir, 403);
                    }
                } else {
                    abort_if($group !== null, 403);
                    $code = $data['tujuan_kode_rek'];
                    if (RencanaPendapatan::where('tahun_anggaran', $source->tahun_anggaran)->where('nama_blud', $source->nama_blud)->where('kode_rek', $code)->exists()) {
                        throw ValidationException::withMessages(['tujuan_kode_rek' => 'Rekening ini sudah ada. Pilih tujuan yang sudah ada.']);
                    }
                    $destination = new RencanaPendapatan([
                        'tahun_anggaran' => $source->tahun_anggaran,
                        'kode_rek' => $code, 'uraian' => $referensi->rekening($code)['uraian'],
                        'jumlah' => '0.00', 'nama_blud' => $source->nama_blud,
                        'user_id' => $request->user()->id, 'is_hasil_perubahan' => true,
                    ]);
                }
                if ($source->kode_rek === $destination->kode_rek) {
                    throw ValidationException::withMessages(['tujuan_pendapatan_id' => 'Pilih rekening tujuan yang berbeda. Perubahan dalam rekening yang sama melalui Kelola Pelayanan.']);
                }
                [$sourceData, , $sourceTotal] = $this->validateShiftRows($request, $validator, $source, 'sumber_rincian');
                [$destinationData, , $destinationTotal] = $this->validateShiftRows($request, $validator, $destination, 'tujuan_rincian');
                $group ??= (string) Str::uuid();
                $metadata = [
                    'status_perubahan' => 'pending', 'verifikator_perubahan' => 'kepala puskesmas',
                    'rekening_awal' => $source->kode_rek, 'rekening_akhir' => $destination->kode_rek,
                    'pergeseran_id' => $group,
                ];
                $source->fill($metadata + ['uraian_perubahan' => $sourceData['uraian'],
                    'jenis_pelayanan_perubahan' => $sourceData['jenis_pelayanan'], 'jumlah_perubahan' => $sourceTotal])->save();
                $destination->fill($metadata + ['uraian_perubahan' => $destinationData['uraian'],
                    'jenis_pelayanan_perubahan' => $destinationData['jenis_pelayanan'], 'jumlah_perubahan' => $destinationTotal])->save();

                return $source->nama_blud;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['tujuan_kode_rek' => 'Rekening tujuan sudah digunakan. Muat ulang dan pilih rekening yang sudah ada.']);
        }

        return $this->redirect($request, 'Pengajuan perubahan/pergeseran disimpan dan menunggu verifikasi Kepala Puskesmas.', $blud);
    }
}
