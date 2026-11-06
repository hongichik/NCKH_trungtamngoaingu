<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/**
 * Chọn 1 đáp án. Lựa chọn lấy từ câu hỏi (A, B, C…) hoặc cố định theo dạng bài
 * (tuỳ chọn "choices", vd TRUE / FALSE / NOT GIVEN).
 */
class Choice extends Engine
{
    public function key(): string
    {
        return 'choice';
    }

    public function label(): string
    {
        return 'Chọn 1 đáp án';
    }

    public function interaction(): string
    {
        return 'Nút chọn (radio) cho từng câu; lựa chọn có thể là chữ, emoji hoặc ảnh.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        $q = ['type' => 'object', 'required' => ['id', 'answer'], 'properties' => [
            'id' => ['type' => 'string'], 'prompt' => ['type' => 'string'], 'image' => ['type' => 'string'],
            'options' => ['type' => 'array'], 'answer' => ['type' => 'string'], 'explanation' => ['type' => 'string'],
        ]];

        return ['required' => ['questions'], 'properties' => ['questions' => ['type' => 'array', 'items' => $q]]];
    }

    /** @return list<array{key: string, text: string}> */
    public function choicesFor(array $q, QuestionType $type): array
    {
        if ($fixed = $type->option('choices')) {
            return array_map(fn ($k, $v) => ['key' => (string) $k, 'text' => (string) $v], array_keys($fixed), $fixed);
        }

        return self::options($q['options'] ?? []);
    }

    protected function check(array $content, QuestionType $type): array
    {
        $errors = [];
        foreach ($content['questions'] ?? [] as $i => $q) {
            $keys = array_map('strtoupper', array_column($this->choicesFor($q, $type), 'key'));
            if (count($keys) < 2) {
                $errors[] = "Câu {$i}: cần ít nhất 2 lựa chọn";
            } elseif (! in_array(strtoupper((string) ($q['answer'] ?? '')), $keys, true)) {
                $errors[] = "Câu {$i}: đáp án \"".($q['answer'] ?? '').'" không nằm trong các lựa chọn';
            }
        }

        return $errors;
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        foreach ($content['questions'] as &$q) {
            $q['choices'] = $this->choicesFor($q, $type);
            unset($q['answer'], $q['explanation']);
        }

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $parts = [];
        foreach ($content['questions'] as $q) {
            $given = self::str($answer[$q['id']] ?? '');
            $ok = $given !== '' && strtoupper($given) === strtoupper($q['answer']);
            $label = collect($this->choicesFor($q, $type))->firstWhere('key', $q['answer'])['text'] ?? $q['answer'];
            $parts[] = self::part($q['id'], $ok ? 1 : 0, 1, $given, $type->option('choices') ? $label : $q['answer'].'. '.$label);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect($content['questions'])->mapWithKeys(fn ($q) => [$q['id'] => $q['answer']])->all();
    }
}
