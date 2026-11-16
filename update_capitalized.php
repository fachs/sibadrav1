<?php
$files = [
    'c:/laragon/www/Sibadra_v1/resources/views/rba-ringkasan.blade.php',
    'c:/laragon/www/Sibadra_v1/resources/views/rba-ringkasan-perubahan.blade.php',
    'c:/laragon/www/Sibadra_v1/resources/views/pdf/rba-ringkasan.blade.php',
    'c:/laragon/www/Sibadra_v1/resources/views/pdf/rba-ringkasan-perubahan.blade.php',
];

$replacements = [
    "'BELANJA PEGAWAI BLUD'" => "'Belanja Pegawai BLUD'",
    "'BELANJA BARANG DAN JASA BLUD'" => "'Belanja Barang dan Jasa BLUD'",
    "'BELANJA MODAL PERALATAN DAN MESIN BLUD'" => "'Belanja Modal Peralatan dan Mesin BLUD'",
    "'BELANJA MODAL GEDUNG DAN BANGUNAN BLUD'" => "'Belanja Modal Gedung dan Bangunan BLUD'",
    "'BELANJA MODAL JALAN, JARINGAN DAN IRIGASI BLUD'" => "'Belanja Modal Jalan, Jaringan dan Irigasi BLUD'",
    "'JASA LAYANAN BLUD'" => "'Jasa Layanan BLUD'",
    "'HIBAH BLUD'" => "'Hibah BLUD'",
    "'HASIL KERJASAMA BLUD'" => "'Hasil Kerjasama BLUD'",
    "'APBD BLUD'" => "'APBD BLUD'",
    "'LAIN-LAIN PENDAPATAN BLUD YANG SAH'" => "'Lain-lain Pendapatan BLUD yang Sah'",
    "'PENERIMAAN PEMBIAYAAN BLUD'" => "'Penerimaan Pembiayaan BLUD'",
    "'PENGELUARAN PEMBIAYAAN BLUD'" => "'Pengeluaran Pembiayaan BLUD'",
];

foreach ($files as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f);
        
        // Let's also change 'AKUMULASI ' . $prefix3 to 'Akumulasi ' . $prefix3
        $c = str_replace("'AKUMULASI ' . \$prefix3", "'Akumulasi ' . \$prefix3", $c);
        
        foreach ($replacements as $old => $new) {
            $c = str_replace($old, $new, $c);
        }
        
        file_put_contents($f, $c);
        echo "Updated \$f\n";
    }
}
