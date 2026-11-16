<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = \App\Models\RencanaKebutuhan::query()->update(['sumber_dana' => 'BLUD']);

echo "Successfully updated $count records.";
