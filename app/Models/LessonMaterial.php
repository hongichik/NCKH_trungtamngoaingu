<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonMaterial extends Model
{
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'docx', 'pptx', 'xlsx', 'mp3', 'm4a', 'zip'];

    protected $guarded = [];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }
}
