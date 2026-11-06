<?php

namespace App\Support\Exercise;

use App\Models\ExerciseSet;

/** Chấm bài Đọc / Nghe trên máy chủ. */
class Scorer
{
    public function isCorrect(string $type, string $given, string $answer, string $language): bool
    {
        if (trim($given) === '') {
            return false;
        }

        return match ($type) {
            'true_false' => strtolower(trim($given)) === strtolower(trim($answer)),
            'ordering' => Answers::ordering($given) === Answers::ordering($answer),
            'gap_fill' => Answers::normalize($given, $language) === Answers::normalize($answer, $language),
            default => Answers::letter($given) === Answers::letter($answer),
        };
    }

    /**
     * @param  array<string, string>  $answers  code => câu trả lời
     * @return array{items: list<array>, correct: int, total: int, score: float}
     */
    public function score(ExerciseSet $set, array $answers): array
    {
        $language = $set->language();
        $items = [];
        foreach ($set->questions as $q) {
            $given = trim((string) ($answers[$q->code] ?? ''));
            $items[] = [
                'code' => $q->code,
                'type' => $q->type,
                'prompt' => $q->prompt,
                'options' => $q->options ?? [],
                'given' => $given,
                'correct' => $this->isCorrect($q->type, $given, $q->answer, $language),
                'answer' => $q->answer,
                'evidence' => $q->evidence,
                'explanation' => $q->explanation,
            ];
        }
        $correct = count(array_filter($items, fn ($i) => $i['correct']));
        $total = count($items);

        return ['items' => $items, 'correct' => $correct, 'total' => $total, 'score' => $total ? round($correct / $total * 10, 1) : 0.0];
    }
}
