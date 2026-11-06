<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['answers' => 'array', 'result' => 'array', 'files' => 'array', 'score' => 'float', 'teacher_score' => 'float', 'reviewed_at' => 'datetime'];
    }

    /** Điểm dùng để tính: giáo viên đã sửa thì lấy điểm giáo viên. */
    public function finalScore(): float
    {
        return (float) ($this->teacher_score ?? $this->score);
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(ExerciseSet::class, 'exercise_set_id');
    }

    public function unitTest(): BelongsTo
    {
        return $this->belongsTo(UnitTest::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
