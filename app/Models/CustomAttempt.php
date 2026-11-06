<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['answers' => 'array', 'files' => 'array', 'result' => 'array', 'started_at' => 'datetime', 'deadline_at' => 'datetime',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ClassroomCustomExercise::class, 'classroom_custom_exercise_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Điểm cuối cùng: giáo viên sửa (nếu có) thay cho điểm chấm. */
    public function finalScore(): ?float
    {
        return $this->teacher_score !== null ? (float) $this->teacher_score : ($this->score !== null ? (float) $this->score : null);
    }
}
