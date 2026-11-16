<?php

namespace App\Http\Controllers;

use App\Models\PrakiraanMaju;
use App\Models\UnitBlud;
use App\Services\PrakiraanMajuData;
use App\Support\TahunAnggaran;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrakiraanMajuController extends Controller
{
    private function targetBlud(Request $request): string
    {
        $user = $request->user();

        if ($user->hasRole('Superuser') && $request->filled('puskesmas')) {
            return (string) $request->input('puskesmas');
        }

        return (string) $user->upt;
    }

    public function index(Request $request, PrakiraanMajuData $prakiraanData)
    {
        $tahunBaseline = TahunAnggaran::context($request);
        $namaBlud = $this->targetBlud($request);
        $rows = $prakiraanData->rows($namaBlud, $tahunBaseline);

        return view('prakiraan-maju', [
            'rows' => $rows,
            'tahunBaseline' => $tahunBaseline,
            'namaBlud' => $namaBlud,
            'puskesmasList' => $request->user()->hasRole('Superuser') ? UnitBlud::orderBy('nama')->get() : collect(),
        ]);
    }

    public function store(Request $request, PrakiraanMajuData $prakiraanData)
    {
        foreach (['nilai_tahun_1', 'nilai_tahun_2'] as $field) {
            $values = $request->input($field);
            if (is_array($values)) {
                $request->merge([$field => array_map(static function ($value) {
                    if (! is_string($value)) {
                        return $value;
                    }

                    $value = trim($value);
                    if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $value)) {
                        return str_replace(',', '.', str_replace('.', '', $value));
                    }
                    if (preg_match('/^\d+(?:,\d{1,2})$/', $value)) {
                        return str_replace(',', '.', $value);
                    }

                    return $value;
                }, $values)]);
            }
        }

        $data = $request->validate([
            'puskesmas' => ['nullable', 'string', 'max:255'],
            'nilai_tahun_1' => ['required', 'array'],
            'nilai_tahun_1.*' => ['required', 'numeric', 'min:0'],
            'nilai_tahun_2' => ['required', 'array'],
            'nilai_tahun_2.*' => ['required', 'numeric', 'min:0'],
        ]);

        foreach (array_keys(PrakiraanMajuData::JENIS_BELANJA) as $kode) {
            if (! array_key_exists($kode, $data['nilai_tahun_1']) || ! array_key_exists($kode, $data['nilai_tahun_2'])) {
                throw ValidationException::withMessages([
                    'nilai_tahun_1' => 'Lengkapi nilai Tahun +1 dan Tahun +2 untuk semua jenis belanja.',
                ]);
            }
        }

        $namaBlud = $this->targetBlud($request);
        $tahunBaseline = TahunAnggaran::context($request);
        $baseline = $prakiraanData->baselineTotals($namaBlud, $tahunBaseline);
        foreach (PrakiraanMajuData::JENIS_BELANJA as $kode => $uraian) {
            PrakiraanMaju::updateOrCreate(
                ['nama_blud' => $namaBlud, 'tahun_baseline' => $tahunBaseline, 'kode_rek' => $kode],
                [
                    'uraian' => $uraian,
                    'nilai_baseline' => $baseline[$kode] ?? 0,
                    'nilai_tahun_1' => $data['nilai_tahun_1'][$kode],
                    'nilai_tahun_2' => $data['nilai_tahun_2'][$kode],
                ]
            );
        }

        $params = $request->user()->hasRole('Superuser') && $request->filled('puskesmas')
            ? ['puskesmas' => $namaBlud] : [];
        return redirect()->route('prakiraan-maju.index', $params)->with('success', 'Prakiraan Maju berhasil disimpan.');
    }
}
