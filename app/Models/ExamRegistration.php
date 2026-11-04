<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRegistration extends Model
{
    protected $fillable = [
        'registration_code', 'student_id', 'exam_session_id', 'exam_level_id',
        'exam_type', 'price', 'status', 'paid_at', 'confirmed_by',
    ];

    protected $casts = [
        'price' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function examLevel(): BelongsTo
    {
        return $this->belongsTo(ExamLevel::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopeType($query, string $type)
    {
        return $query->where('exam_type', $type);
    }
}
