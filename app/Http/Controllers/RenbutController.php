<?php

namespace App\Http\Controllers;

use App\Models\RencanaKebutuhan;
use App\Models\User;
use App\Models\Akun;
use App\Models\UnitBlud;
use App\Support\TahunRba;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;

class RenbutController extends Controller
{
    private function isPrivilegedRole(\App\Models\User $user): bool
    {
        return $user->hasAnyRole(['superuser', 'kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes']);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->input('search');

        $query = RencanaKebutuhan::withTrashed()
            ->where('tahun_anggaran', TahunRba::selected($request))
            ->with('akuns')
            ->orderByRaw('deleted_at IS NULL DESC');

        // Semua user kecuali Superuser hanya melihat data milik BLUD mereka sendiri
        if (!$user->hasRole('Superuser')) {
            $query->where('nama_blud', $user->upt);
        }

        // Superuser bisa filter per puskesmas via query string
        if ($user->hasRole('Superuser') && request()->has('puskesmas') && request('puskesmas') != '') {
            $query->where('nama_blud', request('puskesmas'));
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('uraian', 'like', '%' . $search . '%')
                  ->orWhere('spesifikasi', 'like', '%' . $search . '%')
                  ->orWhere('nama_unit', 'like', '%' . $search . '%');
            });
        }

        $renbut = $query->paginate(10)->appends($request->query());

        $canManageDanaAkun = true;
        $canSeeUnitBlud = $user->hasRole('Superuser');

        $puskesmasList = [];
        if ($user->hasRole('Superuser')) {
            $puskesmasList = UnitBlud::orderBy('nama')->get();
        }

        return view('rencana-kebutuhan', compact('renbut', 'canManageDanaAkun', 'canSeeUnitBlud', 'search', 'puskesmasList'));
    }

    private function queryRekapPerUnit(Request $request)
    {
        $isSuperuser = $request->user()->hasRole('Superuser');
        $blud = trim((string) ($isSuperuser ? $request->query('puskesmas') : $request->user()->upt));
        $query = RencanaKebutuhan::query();

        // Gunakan cakupan BLUD yang sama dengan Rincian Rencana Kebutuhan.
        // Peran pengguna (misalnya bendahara) bukan nama unit/klaster.
        if (!$isSuperuser || $blud !== '') {
            $blud === ''
                ? $query->whereRaw('1 = 0')
                : $query->whereRaw('LOWER(TRIM(nama_blud)) = ?', [strtolower($blud)]);
        }

        return $query;
    }

    public function downloadPdf(Request $request)
    {
        $user = auth()->user();
        $tahunRba = TahunRba::selected($request);
        $namaUnit = $request->query('nama_unit');

        $query = $this->queryRekapPerUnit($request)->where('tahun_anggaran', $tahunRba);

        if (!empty($namaUnit)) {
            $query->where('nama_unit', $namaUnit);
        }

        $renbut = $query->get()
            ->sortBy('nama_unit', SORT_NATURAL | SORT_FLAG_CASE);

        $grouped = $renbut->groupBy('nama_unit');

        $uptName = $user->upt;
        if ($user->hasRole('Superuser')) {
            $uptName = trim((string) $request->query('puskesmas')) ?: 'Semua BLUD';
        }

        $penandatangan = UnitBlud::query()
            ->whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $uptName))])
            ->first();

        $pdf = Pdf::loadView('pdf.rincian-objek', compact(
            'grouped',
            'user',
            'namaUnit',
            'penandatangan',
            'uptName',
            'tahunRba'
        ))->setPaper('A4', 'landscape');

        $fileName = $namaUnit
            ? 'Renbut-' . $uptName . '-' . $tahunRba . '-' . Str::slug($namaUnit) . '.pdf'
            : 'Renbut-' . $uptName . '-' . $tahunRba . '.pdf';

        return $pdf->download($fileName);
    }

    public function indexPerUnit(Request $request)
    {
        $user = auth()->user();
        $tahunRba = TahunRba::selected($request);

        $canFilter = $user->hasRole('Superuser');
        $selectedBlud = $canFilter ? trim((string) $request->query('puskesmas')) : trim((string) $user->upt);
        $bludLabel = $selectedBlud !== '' ? $selectedBlud : ($canFilter ? 'Semua BLUD' : 'BLUD belum ditentukan');
        $query = $this->queryRekapPerUnit($request);
        $renbut = $query->where('tahun_anggaran', $tahunRba)->get()
            ->sortBy('nama_unit', SORT_NATURAL | SORT_FLAG_CASE);

        $grouped = $renbut->groupBy('nama_unit');

        $puskesmasList = [];
        if ($canFilter) {
            $puskesmasList = UnitBlud::orderBy('nama')->get();
        }

        return view('renbut-per-unit', compact('grouped', 'puskesmasList', 'canFilter', 'selectedBlud', 'bludLabel', 'tahunRba'));
    }

    public function input()
    {
        $renbut = RencanaKebutuhan::all();
        $akuns = Akun::all();
        // $user = User::all();
        return view('rencana-kebutuhan-input', compact('renbut', 'akuns'));
    }

    public function edit($id)
    {
        $renbut = RencanaKebutuhan::where('tahun_anggaran', \App\Support\TahunRba::selected())->findOrFail($id);
        $akuns = Akun::all();
        // $user = User::all();
        return view('rencana-kebutuhan-edit', compact('renbut', 'akuns'));
    }

    public function data()
    {
        $user = auth()->user();

        $query = RencanaKebutuhan::withTrashed()->with('akuns')->where('tahun_anggaran', TahunRba::selected());

        if (!$this->isPrivilegedRole($user)) {
            $query->where('nama_blud', $user->upt)
                ->whereIn('nama_unit', $user->roleNames());
        } elseif (!$user->hasRole('Superuser')) {
            $query->where('nama_blud', $user->upt);
        }

        $data = $query->orderByRaw('deleted_at IS NULL DESC');

        $canManageDanaAkun = true;
        $canSeeUnitBlud = $user->hasRole('Superuser');

        return DataTables::of($data)
        ->addColumn('DT_RowIndex', function ($item) {
            return ''; // Yajra will automatically fill this if we use addIndexColumn, but we can also just let it be or use addIndexColumn() below
        })
        ->addColumn('sumber_dana_html', function ($item) use ($canManageDanaAkun) {
            if (!$canManageDanaAkun) return '-';
            if ($item->trashed()) return $item->sumber_dana ?? '-';
            
            $current = $item->sumber_dana ?? 'Pilih Sumber Dana';
            return '
                <span class="text-sumber-dana text-primary fw-medium" data-id="'.$item->id.'" style="cursor: pointer;">'.$current.'</span>
                <br><br>
                <select class="form-control select-sumber-dana d-none" data-id="'.$item->id.'">
                    <option value="">Pilih Sumber Dana</option>
                    <option value="APBD">APBD</option>
                    <option value="APBN">APBN</option>
                    <option value="BLUD">BLUD</option>
                    <option value="HIBAH">HIBAH</option>
                    <option value="LAINNYA">LAINNYA</option>
                </select>
            ';
        })
        ->addColumn('nama_barang_html', function ($item) use ($canManageDanaAkun) {
            if (!$canManageDanaAkun) return '-';
            $text = $item->akuns?->kode_rek && $item->akuns?->uraian ? $item->akuns->kode_rek . ' - ' . $item->akuns->uraian : null;
            if ($item->trashed()) return $text ?? '-';

            $current = $text ?? 'Pilih Barang/Jasa';
            $val = $item->akun_id ?? '';
            $opt = $item->akuns ? '<option value="'.$item->akun_id.'" selected>'.$text.'</option>' : '';
            
            return '
                <span class="text-akun text-primary fw-medium" data-id="'.$item->id.'" data-value="'.$val.'" style="cursor: pointer;">'.$current.'</span>
                <br><br>
                <select class="form-control select-akun d-none" data-id="'.$item->id.'">
                    '.$opt.'
                </select>
            ';
        })
        ->addColumn('aksi_html', function ($item) {
            if ($item->trashed()) return '<span class="text-muted">-</span>';
            $canEditDelete = is_null($item->sumber_dana) && is_null($item->akun_id);
            $disabled = $canEditDelete ? '' : 'disabled';
            $disabledTitle = $canEditDelete ? '' : 'title="Lengkapi Sumber Dana & Akun terlebih dahulu"';
            
            $editDataAttr = '';
            if ($canEditDelete) {
                $editDataAttr = '
                    data-id="'.htmlspecialchars((string)$item->id).'"
                    data-uraian="'.htmlspecialchars((string)$item->uraian).'"
                    data-jumlah="'.htmlspecialchars((string)$item->jumlah).'"
                    data-satuan="'.htmlspecialchars((string)$item->satuan).'"
                    data-harga="'.htmlspecialchars((string)$item->harga_satuan).'"
                    data-keterangan="'.htmlspecialchars((string)$item->keterangan).'"
                    data-nama_blud="'.htmlspecialchars((string)$item->nama_blud).'"
                    data-nama_unit="'.htmlspecialchars((string)$item->nama_unit).'"
                    data-tahun_anggaran="'.htmlspecialchars((string)$item->tahun_anggaran).'"
                    data-program="'.htmlspecialchars((string)$item->program).'"
                    data-kegiatan="'.htmlspecialchars((string)$item->kegiatan).'"
                    data-subkegiatan="'.htmlspecialchars((string)$item->subkegiatan).'"
                    data-ruang="'.htmlspecialchars((string)$item->ruang).'"
                    data-spesifikasi="'.htmlspecialchars((string)$item->spesifikasi).'"
                    data-sumber_dana="'.htmlspecialchars((string)$item->sumber_dana).'"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEdit"
                ';
            }

            return '
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="javascript:void(0)" class="btn btn-info btn-sm btn-edit '.$disabled.'" '.$editDataAttr.' '.(!$canEditDelete ? 'aria-disabled="true" tabindex="-1"' : '').'>
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="'.route('rencana-kebutuhan.destroy', $item->id).'" method="POST" class="m-0" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?" data-delete-disabled="'.($canEditDelete ? '0' : '1').'">
                        <input type="hidden" name="_token" value="'.csrf_token().'">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-danger btn-sm" '.$disabled.' '.$disabledTitle.'>
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            ';
        })
        ->addColumn('jumlah_fmt', function($item) {
            return number_format($item->jumlah, 0, ',', '.');
        })
        ->addColumn('harga_fmt', function($item) {
            return 'Rp ' . number_format($item->harga_satuan, 0, ',', '.');
        })
        ->addColumn('total_fmt', function($item) {
            return 'Rp ' . number_format($item->jumlah_harga, 0, ',', '.');
        })
        ->rawColumns(['sumber_dana_html', 'nama_barang_html', 'aksi_html'])
        ->addIndexColumn()
        ->make(true);
    }

    public function select2(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $data = Akun::query()
            ->select(['id', 'kode_rek', 'uraian'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('kode_rek', 'like', '%' . $search . '%')
                        ->orWhere('uraian', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('kode_rek')
            ->limit(20)
            ->get();

        return response()->json([
            'results' =>
            $data->map(function ($item) {
                return [
                    'id' => $item->id, // yang disimpan
                    'text' => $item->kode_rek . ' - ' . $item->uraian // yang ditampilkan
                ];
            })
        ]);
    }

    public function updateAkun(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|min:1',
            'akun_id' => 'nullable|exists:akuns,id'
        ]);

        $item = $this->queryDanaAkun($request)->findOrFail($request->id);
        $item->akun_id = $request->akun_id;
        $item->save();

        return response()->json([
            'success' => true
        ]);
    }
    
    public function updateSumberDana(Request $request)
    {
        $request->validate([
        'id' => 'required|integer|min:1',
        'sumber_dana' => 'nullable|in:APBD,APBN,BLUD,HIBAH,LAINNYA'
        ]);

        $item = $this->queryDanaAkun($request)->findOrFail($request->id);
        $item->sumber_dana = $request->sumber_dana;
        $item->save();

        return response()->json([
            'success' => true
        ]);
    }

    private function queryDanaAkun(Request $request)
    {
        return RencanaKebutuhan::query()->where('tahun_anggaran', TahunRba::selected($request))->when(
            !$request->user()->hasRole('Superuser'),
            fn ($query) => $query->where('nama_blud', $request->user()->upt)
        );
    }

    public function store(Request $request)
    {
        $request->merge(['tahun_anggaran' => (string) TahunRba::selected($request)]);
        $validated = $request->validateWithBag('createRenbut', [
            'nama_blud' => ['required', 'string', 'max:255'],
            'tahun_anggaran' => ['required', 'digits:4'],
            'nama_unit' => ['required', 'string', 'max:255'],
            'program' => ['required', 'string'],
            'kegiatan' => ['required', 'string'],
            'subkegiatan' => ['required', 'string'],
            'uraian' => ['required', 'string', 'max:255'],
            'spesifikasi' => ['required', 'string', 'max:255'],
            'ruang' => ['required', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:255'],
            'keterangan' => ['required', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'min:1'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);
    
        $validated['jumlah_harga'] = $validated['jumlah'] * $validated['harga_satuan'];
    
        RencanaKebutuhan::create($validated);
    
        return redirect()->route('rencana-kebutuhan.index')
            ->with('success', 'Rencana kebutuhan berhasil ditambahkan.');
    }
    
    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_blud' => 'required',
            'nama_unit' => 'required',
            'tahun_anggaran' => 'required',
            'program' => 'required',
            'kegiatan' => 'required',
            'subkegiatan' => 'required',
            'nama_barjas' => 'nullable',
            'jumlah' => 'required',
            'harga_satuan' => 'required',
            'jumlah_harga' => 'required',
            'satuan' => 'required',
            'ruang' => 'required',
            'keterangan' => 'required',
            'sumber_dana' => 'nullable',
            'spesifikasi' => 'required',
            'uraian' => 'required',
        ]);

        $renbut = RencanaKebutuhan::where('tahun_anggaran', \App\Support\TahunRba::selected())->findOrFail($id);
        $renbut->update($request->only([
                'uraian',
                'jumlah',
                'satuan',
                'harga_satuan',
                'keterangan',
                'jumlah_harga',
            ]));

        return redirect()->route('rencana-kebutuhan.index')
            ->with('success', 'Rencana Kebutuhan berhasil diperbarui');
    }

    public function destroy($id)
    {
        // abort_unless(
        //     auth()->check() && auth()->user()->hasAnyRole(['kepala puskesmas', 'klaster 1']),
        //     403,
        //     'Akses ditolak.'
        // );

        $renbut = RencanaKebutuhan::where('tahun_anggaran', \App\Support\TahunRba::selected())->findOrFail($id);
        $renbut->delete();

        return redirect()->route('rencana-kebutuhan.index')
            ->with('success', 'Rencana Kebutuhan berhasil dihapus');
    }

    public function search(Request $request)
    {
        $search = $request->q;

        $data = Akun::where('nama','like',"%$search%")
                ->limit(20)
                ->get();

        return response()->json(
        $data->map(function($item){
            return [
                'id' => $item->id,
                'text' => $item->uraian
            ];
            })
        );
    }
}
