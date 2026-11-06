<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một lần làm bài 4 kỹ năng của học viên cho 1 Unit. */
class UnitTest extends Model
{
    public const STATUS_LABELS = ['in_progress' => 'Đang làm', 'grading' => 'Đang chấm', 'completed' => 'Đã xong', 'abandoned' => 'Bỏ dở'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sections' => 'array', 'skill_scores' => 'array', 'total_score' => 'float', 'finished_at' => 'datetime', 'deadline_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'unit_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    public function currentSection(): ?array
    {
        return $this->sections[$this->current] ?? null;
    }

    /** Xếp loại theo điểm tổng (thang 10). */
    public static function levelFor(float $score): string
    {
        return match (true) {
            $score >= 9 => 'Xuất sắc',
            $score >= 8 => 'Giỏi',
            $score >= 6.5 => 'Khá',
            $score >= 5 => 'Trung bình',
            default => 'Chưa đạt',
        };
    }
}
