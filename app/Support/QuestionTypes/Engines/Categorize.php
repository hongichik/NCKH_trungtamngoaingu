<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/** Phân loại: xếp từng mục vào đúng nhóm. groups: {"Tên nhóm": ["mục 1", "mục 2"]}. */
class Categorize extends Engine
{
    public function key(): string
    {
        return 'categorize';
    }

    public function label(): string
    {
        return 'Phân loại vào nhóm';
    }

    public function interaction(): string
    {
        return 'Mỗi thẻ có các nút nhóm để bấm chọn (hoạt động tốt trên điện thoại).';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['groups'], 'properties' => ['groups' => ['type' => 'object']]];
    }

    /** @return array<string, array{text: string, group: string}> id => mục */
    public function items(array $content): array
    {
        $out = [];
        $n = 1;
        foreach ($content['groups'] as $group => $items) {
            foreach ((array) $items as $text) {
                $out['i'.$n++] = ['text' => (string) $text, 'group' => (string) $group];
            }
        }

        return $out;
    }

    protected function check(array $content, QuestionType $type): array
    {
        return count($content['groups'] ?? []) < 2 ? ['Cần ít nhất 2 nhóm'] : [];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['group_names'] = array_map('strval', array_keys($content['groups']));
        $content['cards'] = array_map(fn ($i) => $i['text'], self::shuffle($this->items($content), $seed));
        unset($content['groups']);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $parts = [];
        foreach ($this->items($content) as $id => $item) {
            $given = self::str($answer[$id] ?? '');
            $parts[] = self::part($id, $given === $item['group'] ? 1 : 0, 1, $given, $item['group'], null, $item['text']);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return array_map(fn ($i) => $i['group'], $this->items($content));
    }
}
