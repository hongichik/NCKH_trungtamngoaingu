<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/**
 * Nói: đọc to, nghe nhắc lại, trả lời câu hỏi, thẻ chủ đề (IELTS Part 2), mô tả tranh, kể chuyện.
 * Có thời gian chuẩn bị + thời gian nói; ghi âm trên trình duyệt. AI / giáo viên chấm theo tiêu chí.
 */
class Speaking extends Engine
{
    public const DEFAULT_CRITERIA = ['Trôi chảy', 'Từ vựng', 'Ngữ pháp', 'Phát âm'];

    public function key(): string
    {
        return 'speaking';
    }

    public function label(): string
    {
        return 'Nói (ghi âm)';
    }

    public function interaction(): string
    {
        return 'Đếm ngược thời gian chuẩn bị → ghi âm (tự dừng khi hết giờ) → nghe lại. Bài đọc to có nút nghe mẫu; bài nhắc lại chỉ phát âm thanh, không hiện chữ.';
    }

    public function criteria(QuestionType $type): array
    {
        return $type->option('criteria', self::DEFAULT_CRITERIA);
    }

    public function rubric(QuestionType $type): string
    {
        return ($type->grading === 'teacher' ? 'Giáo viên' : 'AI').' nghe bản ghi âm và chấm theo: '.implode(' · ', $this->criteria($type)).'.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['properties' => [
            'prompt' => ['type' => 'string'], 'read_text' => ['type' => 'string'], 'repeat_text' => ['type' => 'string'],
            'image' => ['type' => 'string'], 'questions' => ['type' => 'array', 'items' => ['type' => 'string']],
            'cue_card' => ['type' => 'object', 'required' => ['topic', 'points'], 'properties' => [
                'topic' => ['type' => 'string'], 'points' => ['type' => 'array', 'items' => ['type' => 'string']],
            ]],
            'prep_seconds' => ['type' => 'integer'], 'speak_seconds' => ['type' => 'integer'], 'sample_answer' => ['type' => 'string'],
        ]];
    }

    protected function check(array $content, QuestionType $type): array
    {
        foreach (['prompt', 'read_text', 'repeat_text', 'questions', 'cue_card'] as $k) {
            if (! empty($content[$k])) {
                return [];
            }
        }

        return ['Cần ít nhất một trong: prompt, read_text, repeat_text, questions, cue_card'];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        if (isset($content['repeat_text'])) {
            $content['repeat_b64'] = base64_encode($content['repeat_text']);   // không hiện chữ, chỉ để máy đọc
            unset($content['repeat_text']);
        }
        unset($content['sample_answer']);
        $content['criteria'] = $this->criteria($type);
        $content['prep_seconds'] = (int) ($content['prep_seconds'] ?? $type->option('prep_seconds', 0));
        $content['speak_seconds'] = (int) ($content['speak_seconds'] ?? $type->option('speak_seconds', 60));

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $recorded = ! empty($answer['recorded']);
        $parts = array_map(fn ($c, $i) => self::part('c'.($i + 1), 0, 1, $i === 0 && $recorded ? 'Đã ghi âm '.((int) ($answer['seconds'] ?? 0)).'s' : '', 'Chờ chấm', $i === 0 && ! $recorded ? 'Chưa ghi âm' : null, $c), $this->criteria($type), array_keys($this->criteria($type)));

        return ['parts' => $parts, 'pending' => $recorded];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return ['recorded' => true, 'seconds' => (int) ($content['speak_seconds'] ?? 30)];
    }
}
