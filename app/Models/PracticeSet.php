<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeSet extends Model
{
    protected $table = 'practice_sets';

    protected $guarded = [];

    public const STATUS_LABELS = ['generating' => 'AI đang soạn', 'ready' => 'Chưa làm', 'done' => 'Đã làm', 'failed' => 'Lỗi'];

    protected function casts(): array
    {
        return ['source' => 'array', 'questions' => 'array', 'result' => 'array', 'score' => 'float'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
