<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bài giao cho lớp: 1 Unit, các kỹ năng chọn sẵn, chế độ luyện tập / kiểm tra. */
class Assignment extends Model
{
    public const MODES = ['practice' => 'Luyện tập', 'test' => 'Kiểm tra'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sections' => 'array', 'opens_at' => 'datetime', 'due_at' => 'datetime'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'unit_id');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(UnitTest::class);
    }

    public function isOpen(): bool
    {
        return (! $this->opens_at || $this->opens_at->isPast()) && ! $this->isClosed();
    }

    public function isClosed(): bool
    {
        return $this->due_at && $this->due_at->isPast();
    }

    /** Học viên được xem đáp án / giải thích chưa. */
    public function answersVisible(): bool
    {
        return $this->show_answers !== 'after_due' || $this->isClosed();
    }

    /** Trạng thái với 1 học viên: todo | in_progress | done | missed | upcoming. */
    public function statusFor(int $studentId): string
    {
        $tests = $this->tests->where('student_id', $studentId);
        if ($tests->whereIn('status', ['completed', 'grading'])->isNotEmpty()) {
            return 'done';
        }
        if ($tests->where('status', 'in_progress')->isNotEmpty() && $this->isOpen()) {
            return 'in_progress';
        }
        if ($this->opens_at && $this->opens_at->isFuture()) {
            return 'upcoming';
        }

        return $this->isClosed() ? 'missed' : 'todo';
    }

    public function attemptsUsed(int $studentId): int
    {
        return $this->tests->where('student_id', $studentId)->whereIn('status', ['completed', 'grading', 'in_progress'])->count();
    }
}
