<?php

namespace App\Support\QuestionTypes;

/**
 * Tính điểm có cấu hình cho kết quả chấm của một dạng bài.
 *
 * Cấu hình (mặc định theo dạng bài, có thể ghi đè theo từng bài):
 *  - points:  điểm cho mỗi ý đúng (mặc định 1)
 *  - partial: true = câu nhiều ý được điểm từng phần; false = phải đúng hết mới có điểm
 *  - penalty: điểm bị trừ cho mỗi ý sai (bỏ trống không bị trừ), tổng không âm
 *  - scale:   bảng quy đổi: ten | ielts_listening | ielts_reading_academic | ielts_reading_general
 *             | toeic_listening | toeic_reading | vstep | hsk | raw
 */
class Scoring
{
    public const SCALES = [
        'ten' => 'Thang 10',
        'ielts_listening' => 'Band IELTS Listening (số câu đúng / 40)',
        'ielts_reading_academic' => 'Band IELTS Reading Academic (/ 40)',
        'ielts_reading_general' => 'Band IELTS Reading General (/ 40)',
        'ielts_criteria' => 'Band IELTS theo 4 tiêu chí (Viết / Nói)',
        'toeic_listening' => 'Điểm TOEIC Listening (5–495, ước lượng)',
        'toeic_reading' => 'Điểm TOEIC Reading (5–495, ước lượng)',
        'vstep' => 'Bậc VSTEP (3–5)',
        'hsk' => 'Điểm HSK (/100 mỗi phần, đạt từ 60)',
        'raw' => 'Điểm thô',
    ];

    /** [số câu đúng tối thiểu (trên 40), band] — giảm dần. */
    private const IELTS = [
        'ielts_listening' => [[39, 9], [37, 8.5], [35, 8], [32, 7.5], [30, 7], [26, 6.5], [23, 6], [18, 5.5], [16, 5], [13, 4.5], [10, 4], [8, 3.5], [6, 3], [4, 2.5], [2, 2], [1, 1]],
        'ielts_reading_academic' => [[39, 9], [37, 8.5], [35, 8], [33, 7.5], [30, 7], [27, 6.5], [23, 6], [19, 5.5], [15, 5], [13, 4.5], [10, 4], [8, 3.5], [6, 3], [4, 2.5], [2, 2], [1, 1]],
        'ielts_reading_general' => [[40, 9], [39, 8.5], [37, 8], [36, 7.5], [34, 7], [32, 6.5], [30, 6], [27, 5.5], [23, 5], [19, 4.5], [15, 4], [12, 3.5], [9, 3], [6, 2.5], [3, 2], [1, 1]],
    ];

    public const DEFAULTS = ['points' => 1, 'partial' => true, 'penalty' => 0, 'scale' => 'ten'];

    public static function config(QuestionType $type, array $override = []): array
    {
        return array_merge(self::DEFAULTS, $type->scoring, array_filter($override, fn ($v) => $v !== null));
    }

    /**
     * @param  array{parts: list<array{earned: float, max: float, answered?: bool}>, pending?: bool}  $graded  kết quả của Engine::grade()
     * @return array{earned: float, max: float, percent: float, ten: float, scale: string, scaled: string, pending: bool}
     */
    public static function apply(array $graded, array $config): array
    {
        $points = (float) $config['points'];
        $earned = 0.0;
        $max = 0.0;
        foreach ($graded['parts'] as $p) {
            $partMax = (float) $p['max'];
            $partEarned = (float) $p['earned'];
            if (! $config['partial'] && $partEarned < $partMax) {
                $partEarned = 0;
            }
            $max += $partMax * $points;
            $earned += $partEarned * $points;
            if ($config['penalty'] > 0 && ($p['answered'] ?? true) && $partEarned < $partMax) {
                $earned -= (float) $config['penalty'] * ($partMax - $partEarned);
            }
        }
        $earned = max(0.0, round($earned, 2));
        $percent = $max > 0 ? round($earned / $max * 100, 1) : 0.0;
        $pending = (bool) ($graded['pending'] ?? false);

        return [
            'earned' => $earned, 'max' => round($max, 2), 'percent' => $percent, 'ten' => round($percent / 10, 1),
            'scale' => $config['scale'], 'scaled' => $pending ? 'Chờ chấm' : self::convert($config['scale'], $percent, $earned, $max),
            'pending' => $pending,
        ];
    }

    public static function convert(string $scale, float $percent, float $earned = 0, float $max = 0): string
    {
        return match ($scale) {
            'ielts_listening', 'ielts_reading_academic', 'ielts_reading_general' => 'Band '.self::ieltsBand($scale, (int) round($percent / 100 * 40)),
            'ielts_criteria' => 'Band '.self::roundHalf($percent / 100 * 9),
            'toeic_listening' => self::toeic($percent, true).' / 495',
            'toeic_reading' => self::toeic($percent, false).' / 495',
            'vstep' => self::vstep(self::roundHalf($percent / 10)),
            'hsk' => round($percent).' / 100 — '.($percent >= 60 ? 'Đạt' : 'Chưa đạt'),
            'raw' => rtrim(rtrim(number_format($earned, 2, '.', ''), '0'), '.').' / '.rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.'),
            default => number_format($percent / 10, 1).' / 10',
        };
    }

    public static function ieltsBand(string $scale, int $correctOf40): float
    {
        foreach (self::IELTS[$scale] as [$min, $band]) {
            if ($correctOf40 >= $min) {
                return $band;
            }
        }

        return 0;
    }

    /** Band tổng từ các tiêu chí (Viết / Nói): trung bình, làm tròn .25 → .5, .75 → lên 1 (quy tắc IELTS). */
    public static function ieltsOverall(array $bands): float
    {
        if (! $bands) {
            return 0;
        }
        $avg = array_sum($bands) / count($bands);
        $f = $avg - floor($avg);

        return floor($avg) + ($f >= 0.75 ? 1 : ($f >= 0.25 ? 0.5 : 0));
    }

    private static function roundHalf(float $v): float
    {
        return round($v * 2) / 2;
    }

    /** Ước lượng TOEIC từ tỉ lệ đúng (100 câu/phần); ETS không công bố bảng chính thức. */
    public static function toeic(float $percent, bool $listening): int
    {
        $raw = (int) round($percent);              // = số câu đúng trên 100
        $score = $listening ? $raw * 5 + 15 : $raw * 5 - 5;
        $score = $raw <= ($listening ? 5 : 2) ? 5 : $score;

        return (int) max(5, min(495, round($score / 5) * 5));
    }

    public static function vstep(float $ten): string
    {
        return number_format($ten, 1).' — '.match (true) {
            $ten >= 8.5 => 'Bậc 5 (C1)',
            $ten >= 6.0 => 'Bậc 4 (B2)',
            $ten >= 4.0 => 'Bậc 3 (B1)',
            default => 'Chưa đạt bậc 3',
        };
    }
}
