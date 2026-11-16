<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = \App\Models\RencanaKebutuhan::whereNotNull('akun_id')->count();
echo "Total with akun_id: $count\n";

$data = \App\Models\RencanaKebutuhan::with('akuns')->whereNotNull('akun_id')->get();
$unique = [];
foreach($data as $item) {
    if ($item->akuns) {
        $unique[$item->akuns->kode_rek] = true;
    }
}
foreach(array_keys($unique) as $k) {
    echo $k . "\n";
}
