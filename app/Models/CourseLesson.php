<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Bài học của khoá: video hướng dẫn, tài liệu, ghi chú, (bước 5) bài tập tuỳ chỉnh. */
class CourseLesson extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['require_video' => 'boolean', 'is_published' => 'boolean', 'vocabulary' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TextbookUnit::class, 'unit_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(LessonVideo::class, 'lesson_id')->orderBy('position')->orderBy('id');
    }

    /** Bài tập của bài học (chung của khoá: source_classroom_id null; riêng của một lớp: có source_classroom_id). */
    public function exercises(): HasMany
    {
        return $this->hasMany(CustomExercise::class, 'lesson_id')->orderBy('id');
    }

    /** Bài tập học viên của lớp này thấy: bài chung của khoá + bài riêng của lớp (đã xuất bản). */
    public function exercisesFor(Classroom $classroom)
    {
        return $this->exercises()->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('source_classroom_id')->orWhere('source_classroom_id', $classroom->id));
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class, 'lesson_id')->orderBy('position')->orderBy('id');
    }

    /** Thư mục lưu file của bài (đĩa local = storage/app/private, không truy cập trực tiếp từ web). */
    public function storageDir(): string
    {
        return "courses/{$this->course_id}/lessons/{$this->id}";
    }

    /** Mở với lớp lúc này? (lịch riêng của lớp, mặc định mở ngay). */
    public function openFor(Classroom $classroom): bool
    {
        if (! $this->is_published) {
            return false;
        }
        $s = $this->scheduleFor($classroom);

        return ! $s || (! $s->hidden && (! $s->opens_at || Carbon::parse($s->opens_at)->isPast()));
    }

    public function scheduleFor(Classroom $classroom): ?object
    {
        return DB::table('classroom_lessons')->where('classroom_id', $classroom->id)->where('lesson_id', $this->id)->first();
    }

    /** Học viên đã xem xong mọi video của bài chưa. */
    public function videosCompletedBy(int $studentId): bool
    {
        $ids = $this->videos->pluck('id');

        return $ids->isEmpty() || VideoProgress::whereIn('lesson_video_id', $ids)->where('student_id', $studentId)->whereNotNull('completed_at')->count() === $ids->count();
    }

    /** Lưu lịch mở bài của lớp: $rows = [lesson_id => ['opens_at' => ?, 'hidden' => bool]]. */
    public static function saveSchedule(Classroom $classroom, array $rows): void
    {
        $ids = self::where('course_id', $classroom->course_id)->pluck('id')->all();
        foreach ($rows as $lessonId => $row) {
            if (! in_array((int) $lessonId, $ids, true)) {
                continue;
            }
            $opens = ! empty($row['opens_at']) ? Carbon::parse($row['opens_at']) : null;
            $hidden = ! empty($row['hidden']);
            if (! $opens && ! $hidden) {
                DB::table('classroom_lessons')->where('classroom_id', $classroom->id)->where('lesson_id', $lessonId)->delete();

                continue;
            }
            DB::table('classroom_lessons')->updateOrInsert(
                ['classroom_id' => $classroom->id, 'lesson_id' => (int) $lessonId],
                ['opens_at' => $opens, 'hidden' => $hidden, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
