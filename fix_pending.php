<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$count = \App\Models\RencanaKebutuhan::where('status_perubahan', 'pending')->where('verifikator_perubahan', 'dinas kesehatan')->update(['verifikator_perubahan' => 'kepala puskesmas']);
echo "Updated $count records.\n";
