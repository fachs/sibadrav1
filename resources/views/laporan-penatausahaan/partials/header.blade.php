<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h4 class="mb-1">{{ $heading }}</h4>
        <div class="text-muted">{{ $unitLabel }} · Tahun Anggaran {{ $year }}</div>
    </div>
    <div class="d-flex flex-wrap align-items-end gap-2">
        @if($canFilter)
            <form method="GET" action="{{ route($routeName) }}" class="d-flex align-items-end gap-2">
                <div>
                    <label class="form-label mb-1" for="unit-blud-filter">Unit BLUD</label>
                    <select class="form-select" id="unit-blud-filter" name="unit_blud_id">
                        <option value="">Semua Unit BLUD</option>
                        @foreach($unitOptions as $unit)
                            <option value="{{ $unit->id }}" @selected($selectedUnit?->id === $unit->id)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </form>
        @endif
        <button type="button" class="btn btn-outline-secondary d-print-none" onclick="window.print()">
            <i class="ri-printer-line"></i> Cetak
        </button>
    </div>
</div>
