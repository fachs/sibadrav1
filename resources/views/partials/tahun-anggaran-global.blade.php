@php
    $globalYear = \App\Support\TahunAnggaran::context();
    $returnQuery = request()->query();
    foreach (['tahun', 'tahun_anggaran', 'minggu', 'page'] as $returnKey) unset($returnQuery[$returnKey]);
    $yearReturnUrl = request()->getPathInfo().($returnQuery ? '?'.http_build_query($returnQuery) : '');
@endphp
<form method="POST" action="{{ route('tahun-anggaran.update') }}" class="global-budget-year" id="global-budget-year-form" data-global-year="{{ $globalYear }}">
    @csrf
    <input type="hidden" name="kembali" value="{{ $yearReturnUrl }}">
    <label for="rba-tahun" class="mb-0 fw-semibold">Tahun Anggaran</label>
    <select id="rba-tahun" name="tahun" class="form-select form-select-sm" aria-label="Tahun anggaran aktif" onchange="this.form.requestSubmit()">
        @foreach(\App\Support\TahunAnggaran::options() as $globalYearOption)
            <option value="{{ $globalYearOption }}" @selected($globalYear === $globalYearOption)>{{ $globalYearOption }}</option>
        @endforeach
    </select>
    {{-- <button class="btn btn-primary btn-sm" type="submit" title="Terapkan tahun anggaran" aria-label="Terapkan tahun anggaran"><i class="ri-check-line" aria-hidden="true"></i></button> --}}
</form>
