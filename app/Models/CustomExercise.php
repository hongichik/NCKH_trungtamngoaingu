<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Bài tập tuỳ chỉnh — thuộc thư viện của khoá, dựng từ các dạng bài của thư viện (Bước 1), dùng lại cho nhiều lớp. */
class CustomExercise extends Model
{
    public const STATUSES = ['draft' => 'Nháp', 'published' => 'Dùng được', 'hidden' => 'Đã ẩn'];

    public const SKILLS = ['mixed' => 'Tổng hợp', 'reading' => 'Đọc', 'listening' => 'Nghe', 'writing' => 'Viết', 'speaking' => 'Nói', 'vocab' => 'Từ vựng', 'grammar' => 'Ngữ pháp'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scoring' => 'array', 'is_standard' => 'boolean'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function sourceClassroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'source_classroom_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomExerciseItem::class)->orderBy('position')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ClassroomCustomExercise::class);
    }

    /** Đã có học viên làm → không sửa nội dung nữa (nhân bản để sửa). */
    /** Số bài làm đã nộp (đang chấm / đã chấm) ở mọi lớp. */
    public function submittedAttemptCount(): int
    {
        return CustomAttempt::whereIn('classroom_custom_exercise_id', $this->assignments()->pluck('id'))->whereIn('status', ['graded', 'grading'])->count();
    }

    /** Đã có học viên làm bài (dùng để chỉ ẩn thay vì xoá bài; vẫn sửa được nội dung — điểm cũ giữ nguyên). */
    public function locked(): bool
    {
        return CustomAttempt::whereIn('classroom_custom_exercise_id', $this->assignments()->pluck('id'))->exists();
    }

    public function createdBy(object $actor): array
    {
        return ['created_by_type' => $actor instanceof Teacher ? 'teacher' : 'user', 'created_by_id' => $actor->id, 'created_by_name' => $actor->name];
    }

    public function isCreator(object $actor): bool
    {
        return $this->created_by_type === ($actor instanceof Teacher ? 'teacher' : 'user') && (int) $this->created_by_id === (int) $actor->id;
    }

    /** Nhân bản (kèm các phần và file nghe) — bản mới là nháp của người nhân bản. */
    public function duplicate(object $actor, ?int $classroomId = null): self
    {
        $copy = $this->replicate(['is_standard']);
        $copy->fill(['title' => $this->title.' (bản sao)', 'parent_id' => $this->id, 'status' => 'draft', 'source_classroom_id' => $classroomId] + $this->createdBy($actor));
        $copy->save();
        foreach ($this->items as $item) {
            $new = $item->replicate();
            $new->custom_exercise_id = $copy->id;
            $new->save();
            if ($item->audio_path && Storage::disk('local')->exists($item->audio_path)) {
                $dest = "custom-exercises/{$copy->id}/audio/{$new->id}.".pathinfo($item->audio_path, PATHINFO_EXTENSION);
                Storage::disk('local')->copy($item->audio_path, $dest);
                $new->update(['audio_path' => $dest]);
            }
            if ($item->image_path && Storage::disk('local')->exists($item->image_path)) {
                $dest = "custom-exercises/{$copy->id}/images/{$new->id}.".pathinfo($item->image_path, PATHINFO_EXTENSION);
                Storage::disk('local')->copy($item->image_path, $dest);
                $new->update(['image_path' => $dest]);
            }
        }

        return $copy;
    }
}
