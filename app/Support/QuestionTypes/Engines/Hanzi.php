<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/** Viết chữ Hán theo đúng thứ tự nét (Hanzi Writer). Trình duyệt báo số nét sai của từng chữ. */
class Hanzi extends Engine
{
    public function key(): string
    {
        return 'hanzi';
    }

    public function label(): string
    {
        return 'Viết chữ Hán theo thứ tự nét';
    }

    public function interaction(): string
    {
        return 'Khung viết từng chữ bằng chuột / ngón tay; sai 3 nét thì hiện gợi ý.';
    }

    public function rubric(QuestionType $type): string
    {
        return 'Mỗi chữ 1 ý; đúng khi viết xong với không quá '.$type->option('max_mistakes', 3).' nét sai.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['characters'], 'properties' => [
            'characters' => ['type' => 'string'], 'pinyin' => ['type' => 'string'], 'meaning' => ['type' => 'string'],
        ]];
    }

    public function chars(array $content): array
    {
        return array_values(array_filter(preg_split('//u', $content['characters'], -1, PREG_SPLIT_NO_EMPTY), fn ($c) => preg_match('/\p{Han}/u', $c)));
    }

    protected function check(array $content, QuestionType $type): array
    {
        return $this->chars($content) ? [] : ['Cần ít nhất 1 chữ Hán'];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['chars'] = $this->chars($content);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $max = (int) $type->option('max_mistakes', 3);
        $parts = [];
        foreach ($this->chars($content) as $i => $c) {
            $m = $answer['c'.$i] ?? null;
            $done = is_numeric($m);
            $parts[] = self::part('c'.$i, $done && (int) $m <= $max ? 1 : 0, 1, $done ? "{$m} nét sai" : '', "≤ {$max} nét sai", null, $c);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect($this->chars($content))->keys()->mapWithKeys(fn ($i) => ['c'.$i => 0])->all();
    }
}
