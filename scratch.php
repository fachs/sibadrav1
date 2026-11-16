<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$data = \App\Models\RencanaKebutuhan::with('akuns')->whereNotNull('akun_id')->get();
foreach($data as $item) {
    echo "ID: {$item->id}, Kode Rek: {$item->akuns->kode_rek}, Status: {$item->status}\n";
}
