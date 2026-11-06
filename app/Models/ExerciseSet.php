<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bộ bài tập Đọc / Nghe của một Unit. */
class ExerciseSet extends Model
{
    public const SKILLS = ['reading' => 'Đọc', 'listening' => 'Nghe', 'speaking' => 'Nói', 'writing' => 'Viết'];

    public const SKILL_ICONS = ['reading' => 'book', 'listening' => 'headphones', 'speaking' => 'microphone', 'writing' => 'pencil'];

    /** Kỹ năng chấm bằng AI sau khi nộp (chạy nền). */
    public function gradedByAi(): bool
    {
        return in_array($this->skill, ['speaking', 'writing'], true);
    }

    public const STATUS_LABELS = ['generating' => 'Đang sinh', 'draft' => 'Chờ duyệt', 'published' => 'Đã xuất bản', 'failed' => 'Lỗi'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'matrix' => 'array', 'warnings' => 'array', 'published_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'unit_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    public function audioTrack(): BelongsTo
    {
        return $this->belongsTo(AudioTrack::class);
    }

    public function batch(): string
    {
        return 'exercise:'.$this->id;
    }

    public function language(): string
    {
        return $this->unit->textbook->language;
    }
}
