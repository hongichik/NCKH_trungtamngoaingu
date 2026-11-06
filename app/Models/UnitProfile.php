<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'edited' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'unit_id');
    }
}
