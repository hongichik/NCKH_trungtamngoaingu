<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/**
 * Chọn từ danh sách thả xuống cho mỗi ô trống [[id]].
 *  - Mỗi ô có lựa chọn riêng: gaps: {id: {options: [...], answer: "..."}}
 *  - Hoặc dùng chung một danh sách (điền câu vào đoạn, Cambridge gapped text): options: [...], answers: {id: "A"}
 */
class GapSelect extends Engine
{
    public function key(): string
    {
        return 'gap_select';
    }

    public function label(): string
    {
        return 'Điền từ (chọn trong danh sách)';
    }

    public function interaction(): string
    {
        return 'Danh sách thả xuống nằm ngay trong đoạn văn.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['text'], 'properties' => [
            'text' => ['type' => 'string'], 'gaps' => ['type' => 'object'],
            'options' => ['type' => 'array'], 'answers' => ['type' => 'object'],
        ]];
    }

    private function shared(array $content): bool
    {
        return isset($content['options']);
    }

    /** @return list<array{key: string, text: string}> */
    public function choicesFor(array $content, string $id): array
    {
        if ($this->shared($content)) {
            return self::options($content['options']);
        }

        return array_map(fn ($o) => ['key' => (string) $o, 'text' => (string) $o], $content['gaps'][$id]['options'] ?? []);
    }

    private function answerFor(array $content, string $id): string
    {
        return (string) ($this->shared($content) ? ($content['answers'][$id] ?? '') : ($content['gaps'][$id]['answer'] ?? ''));
    }

    protected function check(array $content, QuestionType $type): array
    {
        $errors = [];
        foreach (self::gapIds($content['text'] ?? '') as $id) {
            if (! in_array($this->answerFor($content, $id), array_column($this->choicesFor($content, $id), 'key'), true)) {
                $errors[] = "Ô {$id}: đáp án không nằm trong lựa chọn";
            }
        }

        return self::gapIds($content['text'] ?? '') ? $errors : ['Văn bản chưa có ô trống [[1]]'];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['segments'] = self::segments($content['text']);
        $content['choices'] = collect(self::gapIds($content['text']))->mapWithKeys(fn ($id) => [$id => $this->choicesFor($content, $id)])->all();
        $content['shared'] = $this->shared($content);
        unset($content['answers'], $content['gaps']);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $parts = [];
        foreach (self::gapIds($content['text']) as $id) {
            $given = self::str($answer[$id] ?? '');
            $right = $this->answerFor($content, $id);
            $parts[] = self::part($id, $given === $right ? 1 : 0, 1, $given, $right);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect(self::gapIds($content['text']))->mapWithKeys(fn ($id) => [$id => $this->answerFor($content, $id)])->all();
    }
}
