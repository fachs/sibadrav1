<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::first();
auth()->login($user);

$grouped = \App\Models\RencanaKebutuhan::with('akuns')->get()->groupBy('akun_id');

$view = view('rincian-objek', ['grouped' => $grouped, 'akuns' => collect(), 'puskesmasList' => collect()])->render();
if (strpos($view, 'Belum Diklasifikasikan') !== false) {
    echo "OK: 'Belum Diklasifikasikan' is in the rendered HTML.\n";
} else {
    echo "FAIL: 'Belum Diklasifikasikan' NOT FOUND in HTML.\n";
}
