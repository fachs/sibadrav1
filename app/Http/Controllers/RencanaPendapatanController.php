<?php

namespace App\Http\Controllers;

use App\Models\RencanaPendapatan;
use App\Support\PendapatanPelayananValidator;
use App\Support\TahunRba;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RencanaPendapatanController extends Controller
{
    public function __construct(private PendapatanPelayananValidator $validator)
    {
    }

    private function isSuperuser(Request $request): bool
    {
        return $request->user()->hasRole('Superuser');
    }

    private function query(Request $request)
    {
        return RencanaPendapatan::query()->where('tahun_anggaran', TahunRba::selected($request))->where(function ($query) {
            $query->where('is_hasil_perubahan', false)->orWhereNull('is_hasil_perubahan');
        })->when(!$this->isSuperuser($request), fn ($query) => $query->where('nama_blud', $request->user()->upt));
    }

    public function create(Request $request)
    {
        return redirect()->route('rba.pendapatan', array_filter([
            'tambah' => 1, 'puskesmas' => $this->isSuperuser($request) ? $request->query('puskesmas') : null,
            'tahun' => $request->input('tahun'),
        ]));
    }

    public function edit(Request $request, int $id)
    {
        $pendapatan = $this->query($request)->findOrFail($id);

        return redirect()->route('rba.pendapatan', array_filter([
            'edit' => $pendapatan->id, 'puskesmas' => $this->isSuperuser($request) ? $pendapatan->nama_blud : null,
            'tahun' => $request->input('tahun'),
        ]));
    }

    public function store(Request $request)
    {
        [$data, $blud, $total] = $this->validator->validate($request);
        $this->saveTransaction(function () use ($data, $blud, $total, $request) {
            $pendapatan = RencanaPendapatan::create([
                'tahun_anggaran' => $data['tahun_anggaran'],
                'kode_rek' => $data['kode_rek'], 'uraian' => $data['uraian'], 'jumlah' => $total,
                'nama_blud' => $blud, 'user_id' => $request->user()->id, 'is_hasil_perubahan' => false,
            ]);
            $pendapatan->jenisPelayanans()->createMany($data['jenis_pelayanan']);
        });

        return $this->redirect($request, $blud, 'Rencana pendapatan dan rincian pelayanan berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $blud = $this->saveTransaction(function () use ($request, $id) {
            $pendapatan = $this->query($request)->lockForUpdate()->findOrFail($id);
            [$data, $blud, $total] = $this->validator->validate($request, $pendapatan);
            $pendapatan->update(['kode_rek' => $data['kode_rek'], 'uraian' => $data['uraian'], 'jumlah' => $total, 'nama_blud' => $blud]);
            $pendapatan->jenisPelayanans()->delete();
            $pendapatan->jenisPelayanans()->createMany($data['jenis_pelayanan']);

            return $blud;
        });

        return $this->redirect($request, $blud, 'Rincian pelayanan dan total pendapatan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $blud = DB::transaction(function () use ($request, $id) {
            $pendapatan = $this->query($request)->lockForUpdate()->findOrFail($id);
            $blud = $pendapatan->nama_blud;
            $pendapatan->jenisPelayanans()->delete();
            $pendapatan->delete();

            return $blud;
        });

        return $this->redirect($request, $blud, 'Rencana pendapatan beserta rincian pelayanan berhasil dihapus.');
    }

    private function redirect(Request $request, string $blud, string $message)
    {
        return redirect()->route('rba.pendapatan', array_filter([
            'puskesmas' => $this->isSuperuser($request) ? $blud : null, 'tahun' => $request->input('tahun'),
        ]))
            ->with('success', $message);
    }

    private function saveTransaction(callable $operation)
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'kode_rek' => 'Kode rekening ini sudah memiliki rencana pendapatan untuk BLUD tersebut.',
            ]);
        }
    }
}
