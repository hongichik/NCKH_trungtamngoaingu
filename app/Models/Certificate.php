<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'numbering', 'exam_date', 'exam_place', 'exam_room',
        'practice_score', 'theoretical_score', 'scores', 'classification',
        'certificate_type', 'serial_number', 'certificate_number',
        'issue_date', 'issue_place', 'status', 'description',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'issue_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
