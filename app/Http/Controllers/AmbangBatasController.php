<?php

namespace App\Http\Controllers;

use App\Models\AmbangBatas;
use App\Models\UnitBlud;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AmbangBatasController extends Controller
{
    private function isSuperuser(Request $request): bool
    {
        return $request->user()->hasRole('Superuser');
    }

    private function scopedQuery(Request $request)
    {
        return AmbangBatas::query()->when(!$this->isSuperuser($request), function ($query) use ($request) {
            $query->where('nama_blud', $request->user()->upt);
        });
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'puskesmas' => ['nullable', 'string', 'max:255'],
            'edit' => ['nullable', 'integer', 'min:1'],
        ]);
        $isSuperuser = $this->isSuperuser($request);
        $puskesmas = $isSuperuser ? ($filters['puskesmas'] ?? '') : '';
        $query = $this->scopedQuery($request);
        if ($puskesmas !== '') {
            $query->where('nama_blud', $puskesmas);
        }
        $ambangBatas = $query->orderByDesc('tahun')->orderBy('nama_blud')
            ->paginate(15)->appends($request->only('puskesmas'));
        $editing = null;
        if (!empty($filters['edit'])) {
            $editing = $this->scopedQuery($request)->findOrFail($filters['edit']);
        }
        $puskesmasList = $isSuperuser ? UnitBlud::orderBy('nama')->get() : collect();

        return view('ambang-batas', compact(
            'ambangBatas', 'isSuperuser', 'puskesmas', 'puskesmasList', 'editing'
        ));
    }

    private function validatedData(Request $request, ?AmbangBatas $ambangBatas = null): array
    {
        $blud = $request->user()->upt;
        if ($this->isSuperuser($request)) {
            $blud = $request->validate([
                'nama_blud' => ['required', 'string', 'max:255', Rule::exists('unit_bluds', 'nama')],
            ])['nama_blud'];
        }
        abort_if(empty($blud), 422, 'Unit BLUD pengguna belum ditentukan.');

        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'between:1900,9999',
                Rule::unique('ambang_batas', 'tahun')->where('nama_blud', $blud)->ignore($ambangBatas?->id)],
            'anggaran' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'realisasi' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
        ], [
            'tahun.unique' => 'Data ambang batas untuk tahun dan BLUD tersebut sudah ada.',
            'anggaran.gt' => 'Anggaran harus lebih besar dari nol agar persentase dapat dihitung.',
        ]);

        return $validated + ['nama_blud' => $blud];
    }

    public function store(Request $request)
    {
        $record = AmbangBatas::create($this->validatedData($request));

        return $this->redirectToIndex($request, $record->nama_blud, 'Data ambang batas berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $record = $this->scopedQuery($request)->findOrFail($id);
        $record->update($this->validatedData($request, $record));

        return $this->redirectToIndex($request, $record->nama_blud, 'Data ambang batas berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $record = $this->scopedQuery($request)->findOrFail($id);
        $blud = $record->nama_blud;
        $record->delete();

        return $this->redirectToIndex($request, $blud, 'Data ambang batas berhasil dihapus.');
    }

    private function redirectToIndex(Request $request, string $blud, string $message)
    {
        return redirect()->route('rba.ambang-batas', $this->isSuperuser($request) ? ['puskesmas' => $blud] : [])
            ->with('success', $message);
    }
}
