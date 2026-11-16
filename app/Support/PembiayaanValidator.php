<?php

namespace App\Support;

use App\Models\RencanaPembiayaan;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PembiayaanValidator
{
    public function validate(Request $request): array
    {
        $rules = [
            'kode_rek' => ['required', 'string', Rule::in(ReferensiPembiayaan::codes())],
            'uraian' => ['required', 'string', 'max:255'],
            'harga_satuan' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
        ];
        for ($i = 1; $i <= 4; $i++) {
            $rules['volume_'.$i] = [$i === 1 ? 'required' : 'nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999.9999'];
            $rules['satuan_'.$i] = [$i === 1 ? 'required' : 'nullable', 'string', 'max:100'];
        }
        $data = $request->validate($rules);
        for ($i = 1; $i <= 4; $i++) {
            $data['volume_'.$i] = $data['volume_'.$i] ?? 1;
            $data['satuan_'.$i] = $data['satuan_'.$i] ?? null;
        }
        $data['jumlah'] = RencanaPembiayaan::hitungJumlah($data);
        if (BigDecimal::of($data['jumlah'])->isGreaterThan('9999999999999.99')) {
            throw ValidationException::withMessages(['harga_satuan' => 'Jumlah pembiayaan melebihi batas Rp 9.999.999.999.999,99. Periksa harga dan volume.']);
        }

        return $data;
    }
}
