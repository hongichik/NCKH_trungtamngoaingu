<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/** Chọn nhiều đáp án (vd IELTS "Choose TWO letters"). Mỗi đáp án đúng là 1 ý; chọn thừa bị trừ ý. */
class MultiChoice extends Engine
{
    public function key(): string
    {
        return 'multi_choice';
    }

    public function label(): string
    {
        return 'Chọn nhiều đáp án';
    }

    public function interaction(): string
    {
        return 'Ô đánh dấu (checkbox); giới hạn đúng số đáp án cần chọn.';
    }

    public function rubric(QuestionType $type): string
    {
        return 'Mỗi đáp án đúng được chọn = 1 ý (điểm từng phần). Mỗi lựa chọn thừa làm mất 1 ý đúng.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['questions'], 'properties' => ['questions' => ['type' => 'array', 'items' => [
            'type' => 'object', 'required' => ['id', 'options', 'answers'], 'properties' => [
                'id' => ['type' => 'string'], 'prompt' => ['type' => 'string'],
                'options' => ['type' => 'array'], 'answers' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ]]]];
    }

    protected function check(array $content, QuestionType $type): array
    {
        $errors = [];
        foreach ($content['questions'] ?? [] as $i => $q) {
            $keys = array_column(self::options($q['options'] ?? []), 'key');
            if (array_diff($q['answers'] ?? [], $keys) || count($q['answers'] ?? []) < 1) {
                $errors[] = "Câu {$i}: đáp án không khớp lựa chọn";
            }
        }

        return $errors;
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        foreach ($content['questions'] as &$q) {
            $q['choices'] = self::options($q['options']);
            $q['choose'] = count($q['answers']);
            unset($q['answers']);
        }

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $parts = [];
        foreach ($content['questions'] as $q) {
            $given = array_values(array_unique(array_map('strtoupper', array_filter((array) ($answer[$q['id']] ?? []), 'is_string'))));
            $right = array_map('strtoupper', $q['answers']);
            $hits = count(array_intersect($given, $right));
            $extra = max(0, count($given) - count($right));
            $parts[] = self::part($q['id'], max(0, $hits - $extra), count($right), $given, $right);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect($content['questions'])->mapWithKeys(fn ($q) => [$q['id'] => $q['answers']])->all();
    }
}
