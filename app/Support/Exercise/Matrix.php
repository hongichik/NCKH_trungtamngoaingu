<?php

namespace App\Support\Exercise;

/**
 * Ma trận đề: số câu từng dạng + tỉ lệ mức độ. Mã câu R1.. (Đọc), L1.. (Nghe).
 */
class Matrix
{
    public const READING = [
        'standard' => ['label' => 'Chuẩn — 15 câu', 'mcq' => 5, 'true_false' => 4, 'cloze' => 5, 'ordering' => 1, 'passage_words' => 250],
        'short' => ['label' => 'Ngắn — 8 câu', 'mcq' => 4, 'true_false' => 3, 'cloze' => 0, 'ordering' => 1, 'passage_words' => 180],
        'long' => ['label' => 'Dài — 22 câu', 'mcq' => 8, 'true_false' => 4, 'cloze' => 8, 'ordering' => 2, 'passage_words' => 320],
    ];

    public const LISTENING = [
        'standard' => ['label' => 'Chuẩn — 8 câu', 'mcq' => 5, 'gap_fill' => 3],
        'short' => ['label' => 'Ngắn — 5 câu', 'mcq' => 3, 'gap_fill' => 2],
    ];

    public const SPEAKING = [
        'standard' => ['label' => 'Chuẩn — 4 nhiệm vụ + 4 câu đọc to', 'speaking' => 4, 'read_aloud' => 4],
        'short' => ['label' => 'Ngắn — 2 nhiệm vụ + 2 câu đọc to', 'speaking' => 2, 'read_aloud' => 2],
    ];

    public const WRITING = [
        'standard' => ['label' => 'Chuẩn — 5 câu viết lại + 1 đoạn văn', 'transformation' => 5, 'paragraph' => 1],
        'short' => ['label' => 'Ngắn — 3 câu viết lại + 1 đoạn văn', 'transformation' => 3, 'paragraph' => 1],
    ];

    public const LEVELS = 'recognition (Nhận biết) ~40%, comprehension (Thông hiểu) ~40%, application (Vận dụng) ~20%';

    public static function get(string $skill, string $preset): array
    {
        $all = match ($skill) {
            'listening' => self::LISTENING,
            'speaking' => self::SPEAKING,
            'writing' => self::WRITING,
            default => self::READING,
        };

        return $all[$preset] ?? $all['standard'];
    }

    /**
     * @return list<array{type: string, codes: list<string>}>
     */
    public static function plan(string $skill, array $m): array
    {
        [$prefix, $types] = match ($skill) {
            'listening' => ['L', ['mcq', 'gap_fill']],
            'speaking' => ['S', ['speaking', 'read_aloud']],
            'writing' => ['W', ['transformation', 'paragraph']],
            default => ['R', ['mcq', 'true_false', 'cloze', 'ordering']],
        };
        $n = 0;
        $plan = [];
        foreach ($types as $t) {
            $count = (int) ($m[$t] ?? 0);
            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                $codes[] = $prefix.(++$n);
            }
            if ($codes) {
                $plan[] = ['type' => $t, 'codes' => $codes];
            }
        }

        return $plan;
    }

    /** Mô tả ma trận cho prompt. */
    public static function planText(string $skill, array $m): string
    {
        $range = fn ($c) => count($c) === 1 ? $c[0] : $c[0].'-'.end($c);
        $lines = [];
        foreach (self::plan($skill, $m) as $p) {
            $c = $p['codes'];
            $lines[] = match ($p['type']) {
                'mcq' => '- '.count($c).' multiple-choice questions (4 options "A. ..." to "D. ..."), ids '.$range($c),
                'true_false' => '- '.count($c).' True/False questions, ids '.$range($c).' (options exactly ["True", "False"], answer "True" or "False")',
                'cloze' => '- 1 cloze passage with '.count($c).' gaps (4 options each), ids '.$range($c).'. Put the cloze text in "cloze_passage" with gaps written as '.implode(', ', array_map(fn ($i) => '('.($i + 1).')', array_keys($c))).'. Each cloze question prompt is "Gap (n)".',
                'ordering' => '- '.count($c).' sentence-ordering task(s), id(s) '.implode(', ', $c).'. Each has 5 sentences forming a paragraph or dialogue; options are the shuffled sentences labelled "a. ...", "b. ..."; answer is the correct order of letters, e.g. "c-a-e-b-d".',
                'gap_fill' => '- '.count($c).' gap-fill questions, ids '.$range($c).': prompt contains "____", options [], answer is 1-3 words heard EXACTLY in the script.',
                // Nói / Viết có prompt riêng (speaking.txt, writing.txt)
                default => '',
            };
        }
        if ($skill === 'reading' && (int) ($m['cloze'] ?? 0) === 0) {
            $lines[] = '- No cloze passage: set "cloze_passage" to "".';
        }

        return implode("\n", array_filter($lines));
    }
}
