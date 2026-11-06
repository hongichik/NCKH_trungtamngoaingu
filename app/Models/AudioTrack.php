<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioTrack extends Model
{
    public const STATUS_LABELS = ['pending' => 'Chưa chép lời', 'transcribing' => 'Đang chép lời', 'done' => 'Đã chép lời', 'failed' => 'Lỗi'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['match_score' => 'float'];
    }

    public function textbook(): BelongsTo
    {
        return $this->belongsTo(Textbook::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'unit_id');
    }

    public function suggestedUnit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'suggested_unit_id');
    }
}
