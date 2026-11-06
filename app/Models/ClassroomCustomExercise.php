<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bài tập tuỳ chỉnh đã thêm vào một lớp (giờ mở, hạn nộp, số lượt, tính giờ). */
class ClassroomCustomExercise extends Model
{
    public const SHOW_ANSWERS = ['after_submit' => 'Ngay sau khi nộp', 'after_due' => 'Sau hạn nộp', 'never' => 'Không hiện'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'due_at' => 'datetime'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(CustomExercise::class, 'custom_exercise_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CustomAttempt::class);
    }

    public function isOpen(): bool
    {
        return (! $this->opens_at || $this->opens_at->isPast()) && ! $this->isClosed();
    }

    public function isClosed(): bool
    {
        return $this->due_at && $this->due_at->isPast();
    }

    public function answersVisible(): bool
    {
        return match ($this->show_answers) {
            'never' => false,
            'after_due' => $this->isClosed(),
            default => true,
        };
    }

    public function timeLimit(): ?int
    {
        return $this->time_limit_minutes ?: $this->exercise?->time_limit_minutes;
    }
}
