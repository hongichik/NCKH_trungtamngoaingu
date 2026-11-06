<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/** Gạch chân / chọn từ trong đoạn theo yêu cầu (vd "chọn tất cả động từ quá khứ"). Từ đúng đánh dấu *…*. */
class Highlight extends Engine
{
    public function key(): string
    {
        return 'highlight';
    }

    public function label(): string
    {
        return 'Gạch chân từ trong đoạn';
    }

    public function interaction(): string
    {
        return 'Bấm vào từ để gạch chân, bấm lần nữa để bỏ.';
    }

    public function rubric(QuestionType $type): string
    {
        return 'Mỗi từ cần chọn = 1 ý. Mỗi từ chọn thừa làm mất 1 ý (khi bật điểm từng phần).';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['prompt', 'text'], 'properties' => ['prompt' => ['type' => 'string'], 'text' => ['type' => 'string']]];
    }

    protected function check(array $content, QuestionType $type): array
    {
        return ErrorCorrection::tokenize($content['text'] ?? '')['marked'] ? [] : ['Chưa đánh dấu từ cần chọn bằng *…*'];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['joiner'] = $type->langOf($content) === 'zh' ? '' : ' ';
        $content['tokens'] = ErrorCorrection::tokenize($content['text'])['tokens'];
        unset($content['text']);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        ['tokens' => $tokens, 'marked' => $marked] = ErrorCorrection::tokenize($content['text']);
        $selected = array_values(array_unique(array_map('intval', (array) ($answer['selected'] ?? []))));
        $parts = [];
        foreach ($marked as $i) {
            $parts[] = self::part('w'.$i, in_array($i, $selected, true) ? 1 : 0, 1, in_array($i, $selected, true) ? $tokens[$i] : '', $tokens[$i], null, $tokens[$i]);
        }
        $extra = array_diff($selected, $marked);
        if ($extra) {
            $hits = count(array_intersect($selected, $marked));
            $parts[] = self::part('extra', -min(count($extra), $hits), 0, array_map(fn ($i) => $tokens[$i] ?? '?', $extra), '(không chọn)', 'Chọn thừa', 'Chọn thừa');
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return ['selected' => ErrorCorrection::tokenize($content['text'])['marked']];
    }
}
