<?php

namespace App\Support\Exercise\Jobs;

use App\Models\AiJob;
use App\Models\ExerciseAttempt;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\Exercise\OpenGrader;

/** Chấm 1 lượt làm bài Nói / Viết của học viên (chạy nền, làn riêng của học viên). */
class GradeAttemptJob implements AiJobHandler
{
    public function __construct(private OpenGrader $grader) {}

    public function handle(AiJob $job): ?array
    {
        $attempt = ExerciseAttempt::find($job->payload['attempt_id'] ?? 0);
        if (! $attempt || $attempt->status === 'graded') {
            return ['skipped' => true];
        }
        $this->grader->grade($attempt);
        app(\App\Support\Exercise\UnitTestService::class)->refreshForAttempt($attempt->fresh());

        return ['score' => $attempt->fresh()->score];
    }

    public function failed(AiJob $job, \Throwable $e): void
    {
        ExerciseAttempt::whereKey($job->payload['attempt_id'] ?? 0)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        if ($a = ExerciseAttempt::find($job->payload['attempt_id'] ?? 0)) {
            app(\App\Support\Exercise\UnitTestService::class)->refreshForAttempt($a); // phần lỗi tính 0 điểm để bài làm không kẹt
        }
    }

    public function retrying(AiJob $job): void
    {
        ExerciseAttempt::whereKey($job->payload['attempt_id'] ?? 0)->update(['status' => 'grading', 'error' => null]);
    }
}
