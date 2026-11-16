<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$akuns = \App\Models\Akun::where('kode_rek', 'like', '5.1%')->take(10)->get();
foreach($akuns as $a) {
    echo $a->kode_rek . " " . $a->uraian . "\n";
}
