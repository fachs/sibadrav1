<?php
$files = [
    'resources/views/rencana-kebutuhan.blade.php',
    'resources/views/rba-perubahan.blade.php',
    'resources/views/rba-pergeseran.blade.php',
    'resources/views/rba-pendapatan.blade.php',
    'resources/views/rba-pendapatan-perubahan.blade.php',
    'resources/views/rba-belanja.blade.php',
    'resources/views/rba-belanja-perubahan.blade.php',
    'resources/views/rba-belanja-perubahan-form.blade.php',
    'resources/views/pencairan-up.blade.php',
    'resources/views/pencairan-ls.blade.php',
    'resources/views/pencairan-gu.blade.php',
    'resources/views/angkas.blade.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // We will use a regex to match the formatRupiah function
    // Assuming formatRupiah doesn't have nested braces in simple ones, 
    // or we can use a more robust regex.
    
    // Pattern for the simple Intl.NumberFormat version
    $pattern_intl = '/[ \t]*function formatRupiah\([a-zA-Z0-9_]*\) \{\s*return new Intl\.NumberFormat\(\'id-ID\'\)\.format\([^\)]+\);\s*\}/s';
    
    // Pattern for the complex version (pencairan)
    $pattern_complex = '/[ \t]*function formatRupiah\([a-zA-Z0-9_]*\) \{\s*if\(![a-zA-Z0-9_]*\) return \'0\';.*?(?=function|\<\/script\>|document\.addEventListener)/s';
    
    // Pattern for angkas.blade.php (it has multiple format functions)
    $pattern_angkas = '/[ \t]*function formatRupiah\(value\) \{\s*const numberValue = Number\(value\) \|\| 0;\s*return \'Rp \' \+ numberValue\.toLocaleString\(\'id-ID\', \{ maximumFractionDigits: 0 \}\);\s*\}/s';
    
    $content = preg_replace($pattern_intl, '', $content);
    $content = preg_replace($pattern_angkas, '', $content);
    
    // For the complex one, we need to be careful not to delete too much.
    // Let's replace the exact known string for the complex one.
    $complex_str = "function formatRupiah(angka) {
            if(!angka) return '0';
            var number_string = angka.toString().replace(/[^,\d]/g, '');
            if(number_string === '') return '0';
            
            var split = number_string.split(',');
            split[0] = split[0].replace(/^0+(?=\d)/, '');
            if(split[0] === '') split[0] = '0';
            
            var sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            return split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        }";
    
    // Replace complex string ignoring exact whitespace
    // Instead of regex, let's just use the regex that matches the body roughly
    $pattern_complex_body = '/[ \t]*function formatRupiah\([a-zA-Z0-9_]*\) \{\s*if\(![a-zA-Z0-9_]*\) return \'0\';\s*var number_string =.*?(?=return split\[1\] \!= undefined \? rupiah \+ \',\'.*?;|$)return split\[1\] \!= undefined \? rupiah \+ \',\' \+ split\[1\] : rupiah;\s*\}/s';
    $content = preg_replace($pattern_complex_body, '', $content);
    
    // Special handling for angkas parseRupiah and formatRupiahModal
    if (basename($file) == 'angkas.blade.php') {
        // We will remove parseRupiah and formatRupiahInput as well if needed?
        // Wait, parseRupiah is used to strip Rp and dots. Our new parseRupiah handles dots and commas.
        // Let's replace parseRupiah in angkas.
        $pattern_parse = '/[ \t]*function parseRupiah\(str\) \{\s*return Number\(String\(str\)\.replace\(\/\[\^0-9\]\/g, \'\'\)\) \|\| 0;\s*\}/s';
        $content = preg_replace($pattern_parse, '', $content);
        
        $pattern_format_input = '/[ \t]*function formatRupiahInput\(input\) \{\s*let val = parseRupiah\(input\.value\);\s*input\.value = val === 0 \? \'0\' : val\.toLocaleString\(\'id-ID\'\);\s*\}/s';
        $content = preg_replace($pattern_format_input, '', $content);
    }
    
    file_put_contents($file, $content);
    echo "Processed $file\n";
}
