<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$total = \App\Models\RencanaKebutuhan::count();
$withAkun = \App\Models\RencanaKebutuhan::whereNotNull('akun_id')->count();
$withoutAkun = \App\Models\RencanaKebutuhan::whereNull('akun_id')->count();
echo "Total records: $total\n";
echo "With akun_id: $withAkun\n";
echo "Without akun_id: $withoutAkun\n";
