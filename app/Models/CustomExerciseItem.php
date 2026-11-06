<?php

namespace App\Models;

use App\Support\QuestionTypes\QuestionType;
use App\Support\QuestionTypes\Registry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một phần của bài tập tuỳ chỉnh = một dạng bài của thư viện + nội dung theo lược đồ của dạng đó. */
class CustomExerciseItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array', 'scoring' => 'array', 'meta' => 'array'];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(CustomExercise::class, 'custom_exercise_id');
    }

    public function type(): ?QuestionType
    {
        return Registry::find($this->type_code);
    }

    /** Nội dung để hiển thị: gắn link file nghe và ảnh của phần (nếu có) vào ngữ liệu. */
    public function contentWithAudio(?string $audioUrl, ?string $imageUrl = null): array
    {
        $c = $this->content;
        if ($audioUrl || $imageUrl) {
            $c['stimulus'] = $c['stimulus'] ?? [];
            if ($audioUrl) {
                $c['stimulus']['audio'] = $audioUrl;
            }
            if ($imageUrl) {
                $c['stimulus']['image'] = $imageUrl;
            }
        }

        return $c;
    }
}
