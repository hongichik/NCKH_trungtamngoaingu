<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần "AI dựng bài từ file": tài liệu + file nghe → bản thiết kế (JSON) → màn hình duyệt → bài tập tuỳ chỉnh. */
class AiImport extends Model
{
    public const STATUSES = [
        'uploading' => 'Đang tải file', 'transcribing' => 'Đang chép lời file nghe', 'ocr' => 'Đang đọc trang scan', 'planning' => 'AI đang chia phần',
        'building' => 'AI đang dựng từng phần', 'review' => 'Chờ duyệt', 'saved' => 'Đã lưu thành bài tập', 'failed' => 'Lỗi',
    ];

    /** Các trạng thái đang xử lý nền (trang tự cập nhật, chạy hàng đợi). */
    public const RUNNING = ['transcribing', 'ocr', 'planning', 'building'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['pages' => 'array', 'audio' => 'array', 'images' => 'array', 'plan' => 'array', 'sections' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(CustomExercise::class, 'custom_exercise_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }

    public function storageDir(): string
    {
        return "ai-imports/{$this->id}";
    }

    public function batch(): string
    {
        return 'ai-import-'.$this->id;
    }

    public function lane(): string
    {
        // Làn con riêng của từng bản nhập ("teacher:5/i12"): nhiều bản nhập của cùng người (vd mọi bài học tự tạo từ giáo trình)
        // được nhiều tiến trình chạy song song; trang tiến độ của người đó vẫn kéo được (khớp tiền tố "teacher:5/").
        $owner = $this->created_by_id ? ($this->created_by_type === 'teacher' ? 'teacher:' : 'user:').$this->created_by_id : 'system';

        return $owner.'/i'.$this->id;
    }
}
