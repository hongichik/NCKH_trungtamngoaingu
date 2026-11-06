<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TextbookPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['needs_ocr' => 'boolean', 'ocr_done' => 'boolean'];
    }

    public function textbook(): BelongsTo
    {
        return $this->belongsTo(Textbook::class);
    }
}
