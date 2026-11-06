<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\Matcher;
use App\Support\QuestionTypes\QuestionType;

/** Kéo thả / bấm chọn từ trong ngân hàng từ để điền vào ô trống [[id]]. Ngân hàng có thể có từ gây nhiễu. */
class GapBank extends Engine
{
    public function key(): string
    {
        return 'gap_bank';
    }

    public function label(): string
    {
        return 'Điền từ (ngân hàng từ)';
    }

    public function interaction(): string
    {
        return 'Kéo thả thẻ từ vào ô trống, hoặc bấm ô rồi bấm thẻ (dùng được trên điện thoại).';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['text', 'bank', 'answers'], 'properties' => [
            'text' => ['type' => 'string'], 'bank' => ['type' => 'array', 'items' => ['type' => 'string']],
            'answers' => ['type' => 'object'], 'reuse' => ['type' => 'boolean'],
        ]];
    }

    protected function check(array $content, QuestionType $type): array
    {
        $errors = [];
        foreach (self::gapIds($content['text'] ?? '') as $id) {
            if (! in_array($content['answers'][$id] ?? null, $content['bank'] ?? [], true)) {
                $errors[] = "Ô {$id}: đáp án không có trong ngân hàng từ";
            }
        }

        return $errors;
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['segments'] = self::segments($content['text']);
        $content['bank'] = array_values(self::shuffle($content['bank'], $seed));
        unset($content['answers']);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $lang = $type->langOf($content);
        $parts = [];
        foreach (self::gapIds($content['text']) as $id) {
            $given = self::str($answer[$id] ?? '');
            $right = (string) $content['answers'][$id];
            $ok = $given !== '' && Matcher::normalize($given, $lang) === Matcher::normalize($right, $lang);
            $parts[] = self::part($id, $ok ? 1 : 0, 1, $given, $right);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect(self::gapIds($content['text']))->mapWithKeys(fn ($id) => [$id => $content['answers'][$id]])->all();
    }
}
