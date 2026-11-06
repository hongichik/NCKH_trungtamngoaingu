<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\Matcher;
use App\Support\QuestionTypes\QuestionType;

/**
 * Điền từ gõ tự do vào ô trống [[id]] trong văn bản (câu, đoạn tóm tắt, bảng "a | b", sơ đồ…).
 * Đáp án: chuỗi hoặc danh sách đáp án chấp nhận; chấm theo Matcher (không phân biệt hoa thường,
 * bỏ dấu câu, số ↔ chữ, từ trong ngoặc là tuỳ chọn) và giới hạn số từ.
 */
class GapText extends Engine
{
    public function key(): string
    {
        return 'gap_text';
    }

    public function label(): string
    {
        return 'Điền từ (gõ tự do)';
    }

    public function interaction(): string
    {
        return 'Ô nhập nằm ngay trong câu / đoạn / bảng; dòng có dấu "|" hiển thị thành bảng.';
    }

    public function rubric(QuestionType $type): string
    {
        $limit = $this->limits([], $type);
        $s = 'Mỗi ô đúng = 1 ý. Không phân biệt hoa thường, bỏ dấu câu, chấp nhận nhiều đáp án, "15" = "fifteen", từ trong ngoặc là tuỳ chọn.';
        if ($limit['max_words']) {
            $s .= " Quá {$limit['max_words']} từ là sai".($limit['numbers_free'] ? ' (con số không tính vào giới hạn)' : '').'.';
        }

        return $s;
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['text', 'answers'], 'properties' => [
            'text' => ['type' => 'string'], 'answers' => ['type' => 'object'], 'max_words' => ['type' => 'integer'],
            'hints' => ['type' => 'object'],
        ]];
    }

    public function limits(array $content, QuestionType $type): array
    {
        return ['max_words' => (int) ($content['max_words'] ?? $type->option('max_words', 0)), 'numbers_free' => (bool) $type->option('numbers_free', false)];
    }

    protected function check(array $content, QuestionType $type): array
    {
        $ids = self::gapIds($content['text'] ?? '');
        $keys = array_map('strval', array_keys($content['answers'] ?? []));
        $errors = [];
        if (! $ids) {
            $errors[] = 'Văn bản chưa có ô trống [[1]]';
        }
        if ($missing = array_diff($ids, $keys)) {
            $errors[] = 'Thiếu đáp án cho ô: '.implode(', ', $missing);
        }

        return $errors;
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['segments'] = self::segments($content['text']);
        $content['limits'] = $this->limits($content, $type);
        unset($content['answers']);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $lang = $type->langOf($content);
        $limits = $this->limits($content, $type);
        $parts = [];
        foreach (self::gapIds($content['text']) as $id) {
            $given = self::str($answer[$id] ?? '');
            $accepted = (array) $content['answers'][$id];
            $r = Matcher::check($given, $accepted, $lang, $limits);
            $parts[] = self::part($id, $r['correct'] ? 1 : 0, 1, $given, $accepted, $r['reason']);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect(self::gapIds($content['text']))->mapWithKeys(fn ($id) => [$id => Matcher::variants((string) ((array) $content['answers'][$id])[0])[0]])->all();
    }
}
