<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Khoá học của hệ thống (quản trị viên tạo); giáo trình nguồn tuỳ chọn; giáo viên được cấp quyền mới sửa được. */
class Course extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'show_reading_preview' => 'boolean'];
    }

    public function textbook(): BelongsTo
    {
        return $this->belongsTo(Textbook::class);
    }

    public function editors(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'course_editors')->withTimestamps();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)->orderBy('position')->orderBy('id');
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    protected static function booted(): void
    {
        // Khoá tạo kèm giáo viên (V1, lệnh demo, test) → giáo viên đó có quyền sửa khoá.
        static::created(function (Course $c) {
            if ($c->teacher_id) {
                $c->editors()->syncWithoutDetaching([$c->teacher_id]);
            }
        });
    }

    /** Giáo viên tạo khoá ở V1 (không còn là chủ sở hữu; quyền sửa xem editors()). */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** Các Unit học viên thấy: không bỏ qua và đã có hồ sơ. */
    public function units()
    {
        return TextbookUnit::where('textbook_id', $this->textbook_id)->where('skipped', false)->where('status', 'ready')->orderBy('position');
    }
}
