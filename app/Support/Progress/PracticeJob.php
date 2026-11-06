<?php

namespace App\Support\Progress;

use App\Models\AiJob;
use App\Models\PracticeSet;
use App\Support\Ai\Ai;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\Exercise\Answers;

/** AI soạn bài luyện riêng từ các câu học viên làm sai (làn riêng của học viên). */
class PracticeJob implements AiJobHandler
{
    public const SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['questions'],
        'properties' => [
            'questions' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['type', 'prompt', 'options', 'answer', 'explanation_vi', 'focus_vi'],
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['mcq', 'true_false', 'gap_fill']],
                    'prompt' => ['type' => 'string'],
                    'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'answer' => ['type' => 'string'],
                    'explanation_vi' => ['type' => 'string'],
                    'focus_vi' => ['type' => 'string'],
                ],
            ]],
        ],
    ];

    public function handle(AiJob $job): ?array
    {
        $set = PracticeSet::with('student')->find($job->payload['practice_id'] ?? 0);
        if (! $set || $set->status !== 'generating') {
            return ['skipped' => true];
        }
        $zh = $set->language === 'zh';
        $mistakes = array_map(fn ($m) => array_intersect_key($m, array_flip(['type', 'prompt', 'options', 'given', 'answer', 'explanation'])), $set->source);
        $count = min(10, max(3, count($mistakes) + 2));

        $r = app(Ai::class)->for($set->student)->json('generate', 'practice', [
            'language' => $zh ? 'Chinese (Simplified)' : 'English',
            'zh_note' => $zh ? ' (Simplified Chinese characters; no pinyin unless the point is pinyin)' : '',
            'mistakes_json' => json_encode($mistakes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'count' => $count,
        ], self::SCHEMA, 'practice_set');

        $questions = [];
        foreach ($r['questions'] ?? [] as $i => $q) {
            $q['prompt'] = trim((string) $q['prompt']);
            $q['answer'] = trim((string) $q['answer']);
            if ($q['prompt'] === '' || $q['answer'] === '') {
                continue;
            }
            if ($q['type'] === 'mcq' && (count($q['options']) < 2 || ! in_array(Answers::letter($q['answer']), array_map(fn ($o) => Answers::letter($o), $q['options']), true))) {
                continue; // đáp án không nằm trong các lựa chọn → bỏ câu
            }
            if ($q['type'] === 'true_false') {
                $q['options'] = [];
                $q['answer'] = str_starts_with(strtolower($q['answer']), 't') ? 'True' : 'False';
            }
            if ($q['type'] === 'gap_fill') {
                $q['options'] = [];
                if (! str_contains($q['prompt'], '__')) {
                    continue;
                }
            }
            $questions[] = ['code' => 'P'.(count($questions) + 1)] + $q;
        }
        if (count($questions) < 2) {
            throw new \RuntimeException('AI chưa soạn được bài luyện hợp lệ.');
        }
        $set->update(['questions' => $questions, 'status' => 'ready', 'error' => null]);

        return ['questions' => count($questions)];
    }

    public function failed(AiJob $job, \Throwable $e): void
    {
        PracticeSet::whereKey($job->payload['practice_id'] ?? 0)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
    }

    public function retrying(AiJob $job): void
    {
        PracticeSet::whereKey($job->payload['practice_id'] ?? 0)->update(['status' => 'generating', 'error' => null]);
    }
}
