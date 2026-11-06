<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TextbookChunk extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['labels' => 'array', 'analysis' => 'array'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'unit_id');
    }

    public function textbook(): BelongsTo
    {
        return $this->belongsTo(Textbook::class);
    }
}
