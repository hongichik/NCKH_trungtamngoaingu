<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonVideo extends Model
{
    public const PROVIDERS = ['local' => 'Tải lên (MP4, phát theo đoạn)', 'hls' => 'Gói HLS mã hoá (zip)', 'bunny' => 'Bunny Stream'];

    protected $guarded = [];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }
}
