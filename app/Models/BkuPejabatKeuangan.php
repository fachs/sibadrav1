<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BkuPejabatKeuangan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rincian_pajak' => 'array',
    ];
}
