<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\Matcher;
use App\Support\QuestionTypes\QuestionType;

/**
 * Trả lời ngắn bằng một câu / cụm từ: viết lại câu (có từ bắt đầu hoặc từ khoá cho sẵn), dịch câu,
 * IELTS short-answer (giới hạn số từ), điền chữ Hán theo pinyin…
 */
class ShortText extends Engine
{
    public function key(): string
    {
        return 'short_text';
    }

    public function label(): string
    {
        return 'Trả lời ngắn / viết lại câu';
    }

    public function interaction(): string
    {
        return 'Ô nhập một dòng cho mỗi câu; có thể điền sẵn phần mở đầu hoặc hiện từ khoá bắt buộc.';
    }

    public function rubric(QuestionType $type): string
    {
        $s = 'So khớp với danh sách đáp án chấp nhận (không phân biệt hoa thường, bỏ dấu câu, số ↔ chữ).';
        if ($max = $type->option('max_words')) {
            $s .= " Quá {$max} từ là sai.";
        }

        return $s;
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['questions'], 'properties' => ['questions' => ['type' => 'array', 'items' => [
            'type' => 'object', 'required' => ['id', 'prompt', 'answers'], 'properties' => [
                'id' => ['type' => 'string'], 'prompt' => ['type' => 'string'], 'start' => ['type' => 'string'],
                'keyword' => ['type' => 'string'], 'answers' => ['type' => 'array', 'items' => ['type' => 'string']],
                'max_words' => ['type' => 'integer'],
            ],
        ]]]];
    }

    protected function check(array $content, QuestionType $type): array
    {
        $errors = [];
        foreach ($content['questions'] ?? [] as $q) {
            if (empty($q['answers'])) {
                $errors[] = "Câu {$q['id']}: chưa có đáp án";
            }
            foreach ($q['answers'] ?? [] as $a) {
                if (! empty($q['keyword']) && ! str_contains(mb_strtolower($a), mb_strtolower($q['keyword']))) {
                    $errors[] = "Câu {$q['id']}: đáp án \"{$a}\" không chứa từ khoá {$q['keyword']}";
                }
            }
        }

        return $errors;
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        foreach ($content['questions'] as &$q) {
            unset($q['answers']);
        }

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $lang = $type->langOf($content);
        $parts = [];
        foreach ($content['questions'] as $q) {
            $given = self::str($answer[$q['id']] ?? '');
            $start = trim($q['start'] ?? '');
            if ($start !== '' && $given === $start) {
                $given = '';      // chỉ còn phần mở đầu điền sẵn = chưa làm
            }
            $limits = ['max_words' => (int) ($q['max_words'] ?? $type->option('max_words', 0)), 'numbers_free' => (bool) $type->option('numbers_free', false)];
            $r = Matcher::check($given, $q['answers'], $lang, $limits);
            if (! $r['correct'] && $start !== '' && $given !== '' && ! str_starts_with(mb_strtolower($given), mb_strtolower($start))) {
                $r = Matcher::check($start.' '.$given, $q['answers'], $lang, []);
            }
            $parts[] = self::part($q['id'], $r['correct'] ? 1 : 0, 1, $given, $q['answers'], $r['reason']);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect($content['questions'])->mapWithKeys(fn ($q) => [$q['id'] => Matcher::variants($q['answers'][0])[0]])->all();
    }
}
