<div class="lpj-signatures">
    <div class="lpj-signature">
        <div>Menyetujui,</div>
        <div>{{ $selectedUnit ? 'KEPALA '.strtoupper($unitLabel) : 'KEPALA UNIT BLUD' }}</div>
        <div class="lpj-signature-name">{{ $selectedUnit?->kepala ?: '________________________' }}</div>
        <div>NIP. {{ $selectedUnit?->nip_kapus ?: '________________________' }}</div>
    </div>
    <div class="lpj-signature">
        <div>Disiapkan oleh,</div>
        <div>PENGGUNA ANGGARAN</div>
        <div class="lpj-signature-name">{{ $selectedUnit?->kepala ?: '________________________' }}</div>
        <div>NIP. {{ $selectedUnit?->nip_kapus ?: '________________________' }}</div>
    </div>
</div>
