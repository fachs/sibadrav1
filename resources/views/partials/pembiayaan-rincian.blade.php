@php $rincian = $item->rincian($perubahan ?? false); @endphp
<details class="{{ ($styledRincian ?? false) ? 'rba-details' : 'mt-2 small' }}">
    <summary @class(['text-primary' => !($styledRincian ?? false)])>@if($styledRincian ?? false)<i class="ri-arrow-right-s-line rba-disclosure-icon" aria-hidden="true"></i><i class="ri-list-check" aria-hidden="true"></i>@endif Volume, satuan, dan harga</summary>
    <dl class="row mb-0 mt-2">
        @for($i = 1; $i <= 4; $i++)
            <dt class="col-5">Volume {{ $i }} / Satuan {{ $i }}</dt>
            <dd class="col-7">{{ rtrim(rtrim(number_format((float) ($rincian['volume_'.$i] ?? 1), 4, ',', '.'), '0'), ',') }} {{ $rincian['satuan_'.$i] ?? '-' }}</dd>
        @endfor
        <dt class="col-5">Harga satuan</dt><dd class="col-7">Rp {{ number_format((float) ($rincian['harga_satuan'] ?? $rincian['jumlah']), 2, ',', '.') }}</dd>
    </dl>
</details>
