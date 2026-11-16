@php $rincian = $item->rincian($perubahan ?? false); @endphp
<div style="font-size: 9px; margin-top: 4px;">
    @for($i = 1; $i <= 4; $i++)
        <div>Volume {{ $i }} / Satuan {{ $i }}: {{ rtrim(rtrim(number_format((float) ($rincian['volume_'.$i] ?? 1), 4, ',', '.'), '0'), ',') }} {{ $rincian['satuan_'.$i] ?? '-' }}</div>
    @endfor
    <div>Harga satuan: Rp {{ number_format((float) ($rincian['harga_satuan'] ?? $rincian['jumlah']), 2, ',', '.') }}</div>
</div>
