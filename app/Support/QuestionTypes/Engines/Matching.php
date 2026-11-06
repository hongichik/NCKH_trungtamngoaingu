<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/**
 * Nối cặp: mỗi mục (đoạn văn, câu, điểm trên bản đồ…) chọn một lựa chọn (tiêu đề, người, kết thúc câu, chữ cái trên bản đồ…).
 * reuse = true: một lựa chọn có thể dùng nhiều lần (IELTS matching features / information).
 */
class Matching extends Engine
{
    public function key(): string
    {
        return 'matching';
    }

    public function label(): string
    {
        return 'Nối cặp';
    }

    public function interaction(): string
    {
        return 'Danh sách lựa chọn có chữ cái; mỗi mục chọn chữ cái tương ứng. Ảnh / bản đồ có thể đánh dấu điểm A, B, C…';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['items', 'options', 'answers'], 'properties' => [
            'items' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'text'], 'properties' => [
                'id' => ['type' => 'string'], 'text' => ['type' => 'string'],
            ]]],
            'options' => ['type' => 'array'], 'answers' => ['type' => 'object'], 'reuse' => ['type' => 'boolean'],
            'options_title' => ['type' => 'string'],
        ]];
    }

    protected function check(array $content, QuestionType $type): array
    {
        $keys = array_column(self::options($content['options'] ?? []), 'key');
        $errors = [];
        foreach ($content['items'] ?? [] as $item) {
            if (! in_array($content['answers'][$item['id']] ?? null, $keys, true)) {
                $errors[] = "Mục {$item['id']}: đáp án không nằm trong lựa chọn";
            }
        }
        $used = array_values($content['answers'] ?? []);
        if (! ($content['reuse'] ?? $type->option('reuse', false)) && count($used) !== count(array_unique($used))) {
            $errors[] = 'Một lựa chọn bị dùng nhiều lần nhưng reuse = false';
        }

        return $errors;
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['choices'] = self::options($content['options']);
        $content['reuse'] = (bool) ($content['reuse'] ?? $type->option('reuse', false));
        unset($content['answers']);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $parts = [];
        foreach ($content['items'] as $item) {
            $given = self::str($answer[$item['id']] ?? '');
            $right = (string) $content['answers'][$item['id']];
            $parts[] = self::part($item['id'], $given === $right ? 1 : 0, 1, $given, $right);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return array_map('strval', $content['answers']);
    }
}
