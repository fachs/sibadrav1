@php
    $routePrefix = $isPerubahan ? 'rba-perubahan.pembiayaan' : 'rba.pembiayaan';
    $user = auth()->user();
    $isPerencanaanOrSuperuser = $isPerencanaanOrSuperuser ?? ($user->hasAnyRole(['Superuser', 'Perencanaan', 'Dinas', 'Dinas Kesehatan']) || $user->isDinasKesehatan());
    $isAllBlud = $isAllBlud ?? ($isPerencanaanOrSuperuser && (!request()->has('puskesmas') || request('puskesmas') == ''));
@endphp
@include('partials.rba-tahun-filter')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 rba-toolbar">
    <div class="d-flex flex-wrap gap-2 align-items-center rba-toolbar-main">
        @if(!$isPerubahan && !$isAllBlud && strcasecmp((string) $user->upt, 'Dinas Kesehatan') !== 0)
            <button type="button" class="btn btn-primary" data-pembiayaan-create data-bs-toggle="modal" data-bs-target="#modalPembiayaan"><i class="ri-add-line"></i> Buat Rencana Pembiayaan</button>
        @endif
        @if($isPerencanaanOrSuperuser)
            <form method="GET" class="d-flex align-items-center gap-2 rba-filter" action="{{ route($routePrefix) }}">
                <input type="hidden" name="tahun" value="{{ $tahunRba }}">
                <label for="puskesmas-filter" class="mb-0 fw-semibold">Pilih BLUD:</label>
                <select id="puskesmas-filter" name="puskesmas" class="form-select form-select-sm" style="min-width: 220px;" onchange="this.form.submit()">
                    <option value="">Semua Unit BLUD (Tampilan Agregat)</option>
                    @foreach($puskesmasList as $unit)<option value="{{ $unit->nama }}" @selected(request('puskesmas') === $unit->nama)>{{ $unit->nama }}</option>@endforeach
                </select>
                @if(request()->filled('puskesmas'))
                    <a href="{{ route($routePrefix, ['tahun' => $tahunRba]) }}" class="btn btn-sm btn-outline-secondary" title="Kembali ke tampilan agregat seluruh BLUD">Reset</a>
                @endif
                <noscript><button type="submit" class="btn btn-primary btn-sm">Tampilkan</button></noscript>
            </form>
        @endif
    </div>
    <div class="d-flex flex-wrap gap-2 rba-toolbar-actions">
        @if($isPerubahan && $canReview)
            @foreach(['verifikasi-semua' => ['Verifikasi Usulan', 'btn-success'], 'tolak-semua' => ['Tolak Usulan', 'btn-danger']] as $action => [$label, $buttonClass])
                <form method="POST" action="{{ route($routePrefix.'.'.$action, $pembiayaanFilters) }}" onsubmit="return confirm('{{ $label }} untuk seluruh usulan pending tahun {{ $tahunRba }}?');">
                    @csrf @method('PATCH')
                    @if(request('puskesmas'))<input type="hidden" name="puskesmas" value="{{ request('puskesmas') }}">@endif
                    <button type="submit" class="btn {{ $buttonClass }}">{{ $label }}</button>
                </form>
            @endforeach
        @endif
        <a href="{{ route($routePrefix.'.pdf', $pembiayaanFilters) }}" class="btn btn-success"><i class="ri-file-pdf-2-line"></i> Unduh PDF</a>
    </div>
</div>
@if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
