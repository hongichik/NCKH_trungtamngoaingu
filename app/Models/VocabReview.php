<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VocabReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['ease' => 'float', 'due_at' => 'datetime', 'last_reviewed_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'unit_id');
    }

    /** Đã "thuộc" khi nhớ liên tiếp đủ lâu (khoảng cách ôn ≥ 21 ngày). */
    public function isMature(): bool
    {
        return $this->interval_days >= 21;
    }
}
