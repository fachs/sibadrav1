<?php
$files = [
    'c:/laragon/www/Sibadra_v1/resources/views/pdf/rba-ringkasan.blade.php',
    'c:/laragon/www/Sibadra_v1/resources/views/pdf/rba-ringkasan-perubahan.blade.php'
];

foreach ($files as $f) {
    $c = file_get_contents($f);
    
    // Remove LEN header
    $c = preg_replace('/<th class="text-center" style="width: 4%;">LEN<\/th>\s*/i', '', $c);
    
    // Remove empty header below LEN
    $c = preg_replace('/<th class="text-center"><\/th>\s*/i', '', $c);
    
    // Remove empty column in body rows
    $c = preg_replace('/<td><\/td>\s*/i', '', $c);
    
    file_put_contents($f, $c);
}
echo "Done";
