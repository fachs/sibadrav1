<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Up extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function unitBlud(): BelongsTo
    {
        return $this->belongsTo(UnitBlud::class);
    }
}
