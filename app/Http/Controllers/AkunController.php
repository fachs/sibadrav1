<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use Illuminate\Http\Request;

class AkunController extends Controller
{
    public function index()
    {
        $akuns = Akun::all();
        // $user = User::all();
        return view('akun', compact('akuns'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'akun' => 'required',
            'kelompok' => 'required',
            'jenis' => 'required',
            'objek' => 'required',
            'rincian_objek' => 'required',
            'sub_ro' => 'required',
            'kode_rek' => 'required',
            'uraian' => 'required',
            'keterangan' => 'required',
        ]);

        Akun::create($request->all());

            //     Alert::success('Congrats', 'You\'ve Successfully Registered');

    //     return redirect()->route('jenis-uji.index');

        return redirect()->route('akun')
            ->with('success', 'Data Akun berhasil ditambahkan');
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
            'results' => $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->kode_rek . ' - ' . $item->uraian,
                ];
            }),
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'akun' => 'required',
            'kelompok' => 'required',
            'jenis' => 'required',
            'objek' => 'required',
            'rincian_objek' => 'required',
            'sub_ro' => 'required',
            'kode_rek' => 'required',
            'uraian' => 'required',
            'keterangan' => 'required',
        ]);

        $akun = Akun::find($id);
        $akun->update($request->all());

        return redirect()->route('akun')
            ->with('success', 'Data Akun berhasil diperbarui');
    }


    public function destroy($id)
    {
        $akun = Akun::find($id);
        $akun->delete();

        return redirect()->route('akun')
            ->with('success', 'Data Akun berhasil dihapus');
    }

    
}
