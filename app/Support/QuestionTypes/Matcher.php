<?php

namespace App\Support\QuestionTypes;

use App\Support\Exercise\Answers;

/**
 * So khớp câu trả lời gõ tự do theo quy tắc:
 *  - không phân biệt hoa thường, bỏ dấu câu, gộp khoảng trắng, nháy cong = nháy thẳng;
 *  - chấp nhận nhiều đáp án; từ trong ngoặc là tuỳ chọn: "(the) museum" nhận "museum" và "the museum";
 *  - chữ số ↔ chữ viết: "15" = "fifteen", "1,000" = "1000";
 *  - giới hạn số từ (vd IELTS "NO MORE THAN TWO WORDS AND/OR A NUMBER": số không tính vào giới hạn).
 */
class Matcher
{
    private const UNITS = ['zero' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7,
        'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14,
        'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19];

    private const TENS = ['twenty' => 20, 'thirty' => 30, 'forty' => 40, 'fifty' => 50, 'sixty' => 60, 'seventy' => 70, 'eighty' => 80, 'ninety' => 90];

    public static function normalize(string $s, string $lang = 'en'): string
    {
        $s = preg_replace('/(?<=\d),(?=\d{3}\b)/', '', $s);     // 1,000 → 1000
        $s = Answers::normalize($s, $lang);
        if ($lang === 'zh') {
            return $s;
        }
        $s = str_replace('-', ' ', $s);                           // well-known = well known
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return self::numbersToDigits($s);
    }

    /** "twenty five" / "twenty-five" → "25", "one hundred and five" → "105". */
    public static function numbersToDigits(string $s): string
    {
        $out = [];
        $cur = null;
        foreach (explode(' ', $s) as $w) {
            $v = self::UNITS[$w] ?? self::TENS[$w] ?? null;
            if ($v !== null) {
                if ($cur !== null && $cur >= 20 && $cur % 10 === 0 && $v < 10 && $cur % 100 !== 0) {
                    $cur += $v;
                } elseif ($cur !== null && $cur >= 100 && $cur % 100 === 0 && $v < 100) {
                    $cur += $v;
                } else {
                    if ($cur !== null) {
                        $out[] = (string) $cur;
                    }
                    $cur = $v;
                }
            } elseif ($cur !== null && ($w === 'hundred' || $w === 'thousand')) {
                $cur *= $w === 'hundred' ? 100 : 1000;
            } elseif ($cur !== null && $w === 'and' && $cur >= 100 && $cur % 100 === 0) {
                continue;
            } else {
                if ($cur !== null) {
                    $out[] = (string) $cur;
                    $cur = null;
                }
                $out[] = $w;
            }
        }
        if ($cur !== null) {
            $out[] = (string) $cur;
        }

        return implode(' ', $out);
    }

    /** Mở rộng "(the) museum" thành ["the museum", "museum"]. */
    public static function variants(string $answer): array
    {
        if (! preg_match('/\(([^()]+)\)/u', $answer, $m)) {
            return [$answer];
        }
        $with = preg_replace('/\(([^()]+)\)/u', '$1', $answer, 1);
        $without = preg_replace('/\s*\([^()]+\)\s*/u', ' ', $answer, 1);

        return array_merge(self::variants($with), self::variants($without));
    }

    public static function wordCount(string $s, string $lang = 'en', bool $numbersFree = false): int
    {
        $s = trim($s);
        if ($s === '') {
            return 0;
        }
        if ($lang === 'zh') {
            return preg_match_all('/\p{Han}/u', $s);
        }
        $words = preg_split('/\s+/u', $s);
        if ($numbersFree) {
            $words = array_filter($words, fn ($w) => ! preg_match('/^[\d.,:\/]+$/', $w));
        }

        return count($words);
    }

    /**
     * @param  list<string>|string  $accepted
     * @param  array{max_words?: int, numbers_free?: bool}  $limits
     * @return array{correct: bool, reason: ?string}
     */
    public static function check(string $given, array|string $accepted, string $lang = 'en', array $limits = []): array
    {
        if (trim($given) === '') {
            return ['correct' => false, 'reason' => 'Bỏ trống'];
        }
        if (($max = (int) ($limits['max_words'] ?? 0)) > 0 && self::wordCount($given, $lang, (bool) ($limits['numbers_free'] ?? false)) > $max) {
            return ['correct' => false, 'reason' => "Quá {$max} từ"];
        }
        $g = self::normalize($given, $lang);
        foreach ((array) $accepted as $a) {
            foreach (self::variants((string) $a) as $v) {
                if ($g === self::normalize($v, $lang)) {
                    return ['correct' => true, 'reason' => null];
                }
            }
        }

        return ['correct' => false, 'reason' => null];
    }

    /** Tỉ lệ đúng theo từ (Levenshtein trên mảng từ) — dùng cho chép chính tả. */
    public static function wordAccuracy(string $given, string $target, string $lang = 'en'): float
    {
        $split = fn ($s) => $lang === 'zh'
            ? preg_split('//u', self::normalize($s, 'zh'), -1, PREG_SPLIT_NO_EMPTY)
            : array_values(array_filter(explode(' ', self::normalize($s, $lang)), 'strlen'));
        $a = $split($given);
        $b = $split($target);
        if (! $b) {
            return $a ? 0.0 : 1.0;
        }
        $prev = range(0, count($b));
        foreach ($a as $i => $x) {
            $cur = [$i + 1];
            foreach ($b as $j => $y) {
                $cur[] = min($prev[$j + 1] + 1, $cur[$j] + 1, $prev[$j] + ($x === $y ? 0 : 1));
            }
            $prev = $cur;
        }

        return max(0.0, 1 - $prev[count($b)] / count($b));
    }
}
