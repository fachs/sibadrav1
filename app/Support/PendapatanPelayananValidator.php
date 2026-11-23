<?php

namespace App\Support;

use App\Models\JenisPelayananPendapatan;
use App\Models\RencanaPendapatan;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PendapatanPelayananValidator
{
    public function __construct(private ReferensiPendapatan $referensi)
    {
    }

    public function validate(Request $request, ?RencanaPendapatan $pendapatan = null, bool $perubahan = false, bool $pergeseran = false): array
    {
        $blud = $perubahan ? $pendapatan->nama_blud : $request->user()->upt;
        if (!$perubahan && $request->user()->hasRole('Superuser')) {
            $blud = $request->validate([
                'nama_blud' => ['required', 'string', Rule::exists('unit_bluds', 'nama')],
            ])['nama_blud'];
        }
        abort_if(empty($blud), 422, 'Unit BLUD pengguna belum ditentukan.');
        $year = $pendapatan?->tahun_anggaran ?? TahunRba::selected($request);
        $rules = [
            'kode_rek' => ['required', 'string', Rule::in($this->referensi->codes()),
                Rule::unique('rencana_pendapatans')->where('nama_blud', $blud)
                    ->where('tahun_anggaran', $year)
                    ->ignore($pendapatan?->id)],
            'jenis_pelayanan' => ['required', 'array', 'min:1', 'max:100'],
            'jenis_pelayanan.*.unit_pelayanan' => ['required', 'string', Rule::in(JenisPelayananPendapatan::unitsFor($blud))],
            'jenis_pelayanan.*.uraian' => ['required', 'string', 'max:255'],
            'jenis_pelayanan.*.harga_satuan' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'jenis_pelayanan.*.gunakan_cagr' => ['sometimes', 'boolean'],
            'jenis_pelayanan.*.realisasi_tahun_3' => ['exclude_unless:jenis_pelayanan.*.gunakan_cagr,1', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'jenis_pelayanan.*.realisasi_tahun_2' => ['exclude_unless:jenis_pelayanan.*.gunakan_cagr,1', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'jenis_pelayanan.*.realisasi_jan_jun' => ['exclude_unless:jenis_pelayanan.*.gunakan_cagr,1', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
        ];
        for ($i = 1; $i <= 4; $i++) {
            $rules['jenis_pelayanan.*.volume_'.$i] = [$i === 1 ? 'required' : 'nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999.9999'];
            $rules['jenis_pelayanan.*.satuan_'.$i] = [$i === 1 ? 'required' : 'nullable', 'string', 'max:100'];
        }
        if ($perubahan) {
            $rules['kode_rek'][] = Rule::in([$pendapatan->kode_rek]);
        }
        if ($pergeseran) {
            $rules['kode_rek'] = ['required', 'string', Rule::in([$pendapatan->kode_rek]), Rule::in($this->referensi->codes())];
        }
        $data = $request->validate($rules, [
            'kode_rek.in' => 'Pilih kode rekening dari daftar referensi pelayanan.',
            'kode_rek.unique' => 'Kode rekening ini sudah memiliki rencana pendapatan. Tambahkan pelayanan melalui Kelola Pelayanan pada rekening tersebut.',
        ]);
        $data['uraian'] = $this->referensi->rekening($data['kode_rek'])['uraian'];
        $data['tahun_anggaran'] = $year;

        $total = BigDecimal::of(0);
        foreach ($data['jenis_pelayanan'] as $index => &$pelayanan) {
            $pelayanan['gunakan_cagr'] = $request->boolean('jenis_pelayanan.'.$index.'.gunakan_cagr');
            // Array induk Laravel dapat memuat key tambahan; hanya simpan kolom yang tervalidasi.
            $pelayanan = array_intersect_key($pelayanan, array_flip([
                'unit_pelayanan', 'uraian', 'gunakan_cagr', 'harga_satuan',
                'volume_1', 'volume_2', 'volume_3', 'volume_4', 'satuan_1', 'satuan_2', 'satuan_3', 'satuan_4',
                'realisasi_tahun_3', 'realisasi_tahun_2', 'realisasi_jan_jun',
            ]));
            $referensi = $this->referensi->pelayanan($data['kode_rek'], $pelayanan['uraian']);
            if (!$referensi) {
                throw ValidationException::withMessages([
                    'jenis_pelayanan.'.$index.'.uraian' => 'Pilih jenis pelayanan yang sesuai dengan kode rekening terpilih.',
                ]);
            }
            if (!$pelayanan['gunakan_cagr']) {
                foreach (['realisasi_tahun_3', 'realisasi_tahun_2', 'realisasi_jan_jun'] as $field) {
                    $pelayanan[$field] = null;
                }
            }
            for ($i = 1; $i <= 4; $i++) {
                $pelayanan['volume_'.$i] = $pelayanan['volume_'.$i] ?? 1;
                $pelayanan['satuan_'.$i] = $pelayanan['satuan_'.$i] ?? null;
            }
            try {
                if ($pelayanan['gunakan_cagr']) {
                    JenisPelayananPendapatan::hitungProyeksiCagr($pelayanan);
                }
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['jenis_pelayanan.'.$index.'.realisasi_jan_jun' => $exception->getMessage()]);
            }
            $pelayanan['jumlah'] = JenisPelayananPendapatan::hitungJumlah($pelayanan);
            $total = $total->plus($pelayanan['jumlah']);
            if ($total->isGreaterThan('9999999999999.99')) {
                throw ValidationException::withMessages([
                    'jenis_pelayanan.'.$index.'.harga_satuan' => 'Jumlah seluruh pelayanan melebihi batas Rp9.999.999.999.999,99. Periksa harga dan volume.',
                ]);
            }
        }
        unset($pelayanan);

        return [$data, $blud, (string) $total->toScale(2)];
    }

}
