<?php

namespace App\Support\Vocab\Jobs;

use App\Models\AiJob;
use App\Models\CourseLesson;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\Vocab\LessonVocabulary;

/** AI lập danh sách từ vựng cho một bài học (chỉ khi bài chưa có danh sách — không ghi đè bản giáo viên đã sửa). */
class LessonVocabularyJob implements AiJobHandler
{
    public function __construct(private LessonVocabulary $vocab) {}

    public function handle(AiJob $job): ?array
    {
        $lesson = CourseLesson::with('course')->find($job->payload['lesson_id'] ?? 0);
        if (! $lesson || ! empty($lesson->vocabulary)) {
            return ['skipped' => true];
        }
        $actor = ($job->payload['actor_type'] ?? '') === 'teacher' ? Teacher::find($job->payload['actor_id'] ?? 0) : User::find($job->payload['actor_id'] ?? 0);
        $words = $this->vocab->generate($lesson, $actor);
        if ($words && empty($lesson->fresh()->vocabulary)) {
            $lesson->update(['vocabulary' => $words]);
        }

        return ['words' => count($words)];
    }
}
