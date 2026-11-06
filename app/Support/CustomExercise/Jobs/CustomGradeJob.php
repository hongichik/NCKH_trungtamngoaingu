<?php

namespace App\Support\CustomExercise\Jobs;

use App\Models\AiJob;
use App\Models\CustomAttempt;
use App\Models\CustomExerciseItem;
use App\Support\Ai\Ai;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\CustomExercise\CustomExerciseService;
use App\Support\QuestionTypes\Registry;
use App\Support\QuestionTypes\Scoring;
use Illuminate\Support\Facades\Storage;

/**
 * AI chấm phần Viết / Nói của bài tập tuỳ chỉnh theo tiêu chí của dạng bài.
 * Mỗi lượt chấm 1 phần (vừa giới hạn ~25 giây của hosting); còn phần chờ thì tự xếp việc tiếp.
 */
class CustomGradeJob implements AiJobHandler
{
    public function __construct(private Ai $ai, private CustomExerciseService $service) {}

    public function handle(AiJob $job): ?array
    {
        $attempt = CustomAttempt::find($job->payload['attempt_id'] ?? 0);
        if (! $attempt || $attempt->status !== 'grading') {
            return ['skipped' => true];
        }
        $itemId = collect($attempt->result ?? [])->filter(fn ($r) => ! empty($r['pending']))->keys()->first();
        if ($itemId === null) {
            return ['done' => true];
        }
        $item = CustomExerciseItem::findOrFail($itemId);
        $type = $item->type();
        $answer = (array) (($attempt->answers ?? [])[$itemId] ?? []);
        $ai = $this->ai->for($attempt->student);
        $lang = $type->langOf($item->content);

        $transcript = null;
        if ($type->engine === 'speaking') {
            $file = ($attempt->files ?? [])[$itemId] ?? null;
            if (! $file || ! Storage::disk('local')->exists($file)) {
                $this->service->gradeItem($attempt, (int) $itemId, 0, ['comment' => 'Không có bản ghi âm.']);

                return $this->next($attempt);
            }
            $transcript = trim($ai->transcribe(Storage::disk('local')->path($file), [
                'languages' => [$lang, 'vi'],
                'prompt' => 'A Vietnamese learner doing a '.($lang === 'zh' ? 'Chinese' : 'English').' speaking task. Transcribe verbatim, do not correct mistakes.',
            ])['text'] ?? '');
            $text = $transcript;
        } else {
            $text = trim((string) ($answer['text'] ?? ''));
        }

        $criteria = $type->engine()->criteria($type);
        $ielts = (Registry::scoring($type, $item->scoring ?? [])['scale'] ?? '') === 'ielts_criteria';
        $max = $ielts ? 9 : 10;
        $task = json_encode(array_intersect_key($item->content, array_flip(['prompt', 'read_text', 'questions', 'cue_card', 'min_words', 'max_words', 'keywords'])), JSON_UNESCAPED_UNICODE);
        $prompt = "Bạn là giám khảo chấm bài {$type->name}. Đề bài (JSON): {$task}\n"
            .($type->engine === 'speaking' ? "Lời học viên nói (bản chép tự động): \"{$text}\"\n" : "Bài viết của học viên:\n\"\"\"\n{$text}\n\"\"\"\n")
            ."Chấm từng tiêu chí trên thang 0–{$max} (cho phép .5): ".implode('; ', $criteria).".\n"
            .'Nhận xét ngắn bằng tiếng Việt cho từng tiêu chí và nhận xét chung (điểm mạnh, lỗi chính, cách sửa). Bài trống hoặc lạc đề thì cho điểm thấp.';
        $schema = ['type' => 'object', 'properties' => [
            'criteria' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'name' => ['type' => 'string'], 'score' => ['type' => 'number'], 'comment' => ['type' => 'string'],
            ], 'required' => ['name', 'score', 'comment']]],
            'overall_comment' => ['type' => 'string'],
        ], 'required' => ['criteria', 'overall_comment']];

        $res = $text === '' ? ['criteria' => [], 'overall_comment' => 'Bài trống.'] : $ai->jsonRaw('grading', $prompt, $schema, 'custom_grade');
        $scores = array_map(fn ($c) => max(0, min($max, (float) $c['score'])), $res['criteria']);
        $avg = $scores ? array_sum($scores) / count($scores) : 0;
        $this->service->gradeItem($attempt, (int) $itemId, $avg / $max, [
            'by' => 'ai', 'criteria' => $res['criteria'], 'comment' => $res['overall_comment'], 'transcript' => $transcript,
            'band' => $ielts && $scores ? Scoring::ieltsOverall($scores) : null,
        ]);

        return $this->next($attempt->fresh());
    }

    private function next(CustomAttempt $attempt): array
    {
        if ($attempt->status === 'grading') {
            AiJob::dispatch('custom_grade', ['attempt_id' => $attempt->id], null, 0, 'student:'.$attempt->student_id);
        }

        return ['score' => $attempt->score, 'status' => $attempt->status];
    }

    public function failed(AiJob $job, \Throwable $e): void
    {
        // Để lại "chờ chấm" — giáo viên chấm tay ở trang bài làm.
    }
}
