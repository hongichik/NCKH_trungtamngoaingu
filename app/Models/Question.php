<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    public const TYPES = [
        'mcq' => 'Trắc nghiệm', 'true_false' => 'Đúng / Sai', 'cloze' => 'Điền vào đoạn văn',
        'ordering' => 'Sắp xếp', 'gap_fill' => 'Điền từ nghe được',
        'speaking' => 'Nhiệm vụ nói', 'read_aloud' => 'Đọc to',
        'transformation' => 'Viết lại câu', 'paragraph' => 'Viết đoạn văn',
    ];

    /** Câu chấm bằng AI (Nói / Viết đoạn) — điểm 0–10 theo tiêu chí, không phải đúng/sai. */
    public const OPEN_TYPES = ['speaking', 'read_aloud', 'paragraph'];

    public const LEVELS = ['recognition' => 'Nhận biết', 'comprehension' => 'Thông hiểu', 'application' => 'Vận dụng'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'issues' => 'array', 'meta' => 'array'];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(ExerciseSet::class, 'exercise_set_id');
    }

    /** Dữ liệu an toàn gửi cho học viên TRƯỚC khi nộp (không có đáp án, căn cứ, giải thích). */
    public function forStudent(): array
    {
        $m = $this->meta ?? [];
        // Bài mẫu (sample_answer) và các đáp án chấp nhận được chỉ hiện SAU khi nộp.
        $safe = array_intersect_key($m, array_flip(['task_type', 'ideas_vi', 'useful_language', 'time_seconds', 'start', 'min_words', 'max_words']));

        return ['code' => $this->code, 'type' => $this->type, 'prompt' => $this->prompt, 'options' => $this->options ?? [], 'meta' => $safe];
    }
}
