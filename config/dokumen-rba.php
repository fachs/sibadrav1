<?php

return [
    'cache_path' => storage_path('app/private/dokumen-rba'),
    'powershell' => env('RBA_POWERSHELL', 'powershell.exe'),

    // Word dan PDF diisi dari template yang sama untuk UPT dan tahun anggaran aktif.
    'templates' => [
        'murni' => [
            'label' => 'RBA Murni',
            'word' => resource_path('documents/rba/template-rba.docx'),
        ],
        'perubahan' => [
            'label' => 'RBA Perubahan',
            'word' => resource_path('documents/rba/template-rba.docx'),
        ],
    ],
];
