<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\Matcher;
use App\Support\QuestionTypes\QuestionType;

/** Sắp xếp từ thành câu. tokens ghi theo thứ tự đúng; accept = các câu đúng khác (nếu có). */
class WordOrder extends Engine
{
    public function key(): string
    {
        return 'word_order';
    }

    public function label(): string
    {
        return 'Sắp xếp từ thành câu';
    }

    public function interaction(): string
    {
        return 'Bấm lần lượt các thẻ từ để ghép câu; bấm "Làm lại" để xếp lại.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['questions'], 'properties' => ['questions' => ['type' => 'array', 'items' => [
            'type' => 'object', 'required' => ['id', 'tokens'], 'properties' => [
                'id' => ['type' => 'string'], 'prompt' => ['type' => 'string'],
                'tokens' => ['type' => 'array', 'items' => ['type' => 'string']], 'accept' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ]]]];
    }

    public function join(array $tokens, string $lang): string
    {
        return implode($lang === 'zh' ? '' : ' ', $tokens);
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['joiner'] = $type->langOf($content) === 'zh' ? '' : ' ';
        foreach ($content['questions'] as &$q) {
            $q['cards'] = array_values(self::shuffle($q['tokens'], $seed.$q['id']));
            unset($q['tokens'], $q['accept']);
        }

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $lang = $type->langOf($content);
        $parts = [];
        foreach ($content['questions'] as $q) {
            $given = self::str($answer[$q['id']] ?? '');
            $accepted = array_merge([$this->join($q['tokens'], $lang)], $q['accept'] ?? []);
            $ok = $given !== '' && in_array(Matcher::normalize($given, $lang), array_map(fn ($a) => Matcher::normalize($a, $lang), $accepted), true);
            $parts[] = self::part($q['id'], $ok ? 1 : 0, 1, $given, $accepted[0]);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect($content['questions'])->mapWithKeys(fn ($q) => [$q['id'] => $this->join($q['tokens'], $type->langOf($content))])->all();
    }
}
