<?php

namespace App\Http\Controllers;

use App\Models\UnitBlud;
use Illuminate\Http\Request;

class UnitBludController extends Controller
{
    public function index()
    {
        $data_blud = UnitBlud::all();
        return view('data-blud', compact('data_blud'));
    }

    private function ensureSuperuser(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->hasRole('Superuser'),
            403,
            'Akses ditolak. Hanya Superuser.'
        );
    }

    public function store(Request $request)
    {
        $this->ensureSuperuser();

        $validated = $request->validateWithBag('createBlud', [
            'nama' => ['required','string','max:255'],
            'kepala' => ['required','string','max:255'],
            'kasubbag_tu' => ['required','string','max:255'],
            'kontak' => ['required','string','max:255'],
            'alamat' => ['required','string','max:255'],
            'nip_kapus' => ['required','string','max:255'],
            'nip_ktu' => ['required','string','max:255'],
        ]);

        UnitBlud::create($validated);

        return redirect()->route('data-blud.index')->with('success', 'UPTD berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $this->ensureSuperuser();

        $unitBlud = UnitBlud::findOrFail($id);

        $validated = $request->validateWithBag('updateBlud', [
            'nama' => ['required', 'string', 'max:255'],
            'kepala' => ['required', 'string', 'max:255'],
            'kasubbag_tu' => ['required', 'string', 'max:255'],
            'kontak' => ['required', 'string', 'max:255'],
            'alamat' => ['required', 'string', 'max:255'],
            'nip_kapus' => ['required', 'string', 'max:255'],
            'nip_ktu' => ['required', 'string', 'max:255'],
        ]);

        $unitBlud->update($validated);

        return redirect()
            ->route('data-blud.index')
            ->with('success', 'UPTD berhasil diupdate.');
    }


    public function destroy($id)
    {
        $this->ensureSuperuser();

        $unitBlud = UnitBlud::findOrFail($id);
        $unitBlud->delete();

        return redirect()
            ->route('data-blud.index')
            ->with('success', 'UPTD berhasil dihapus.');
    }


}
