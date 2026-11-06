<?php

namespace App\Support\Textbook;

/**
 * Tiện ích văn bản dùng chung cho tiếng Anh và tiếng Trung.
 */
class Text
{
    /** Kích thước để chia đoạn: tiếng Anh = số từ; tiếng Trung = số chữ Hán + số từ Latin. */
    public static function size(string $text, string $language): int
    {
        if ($language === 'zh') {
            $han = preg_match_all('/(?![\p{P}\p{S}])\p{Han}/u', $text); // không tính dấu câu kiểu 。，
            $latin = preg_match_all('/[A-Za-z0-9]+/', preg_replace('/\p{Han}/u', ' ', $text));

            return $han + $latin;
        }

        $text = trim($text);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }

    /** Chuẩn hoá để so khớp: chữ thường, gộp khoảng trắng, bỏ dấu nháy kiểu "thông minh". */
    public static function normalize(string $text): string
    {
        $text = str_replace(['“', '”', '‘', '’', '–', '—', "\u{00A0}"], ['"', '"', "'", "'", '-', '-', ' '], $text);

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    /** Số La Mã hoặc số Hán → số nguyên (0 nếu không đọc được). */
    public static function toNumber(string $s): int
    {
        $s = trim($s);
        if ($s === '') {
            return 0;
        }
        if (ctype_digit($s)) {
            return (int) $s;
        }
        if (preg_match('/^[ivxlc]+$/i', $s)) {
            $map = ['i' => 1, 'v' => 5, 'x' => 10, 'l' => 50, 'c' => 100];
            $total = 0;
            $chars = str_split(strtolower($s));
            foreach ($chars as $i => $c) {
                $v = $map[$c];
                $next = isset($chars[$i + 1]) ? $map[$chars[$i + 1]] : 0;
                $total += $v < $next ? -$v : $v;
            }

            return $total;
        }

        // Số Hán: 一 二 ... 十 百, 两 = 2, 〇/零 = 0
        $digits = ['零' => 0, '〇' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9];
        $total = 0;
        $current = 0;
        foreach (mb_str_split($s) as $ch) {
            if (isset($digits[$ch])) {
                $current = $digits[$ch];
            } elseif ($ch === '十') {
                $total += ($current ?: 1) * 10;
                $current = 0;
            } elseif ($ch === '百') {
                $total += ($current ?: 1) * 100;
                $current = 0;
            } elseif (ctype_digit($ch)) {
                $current = $current * 10 + (int) $ch;
            } else {
                return 0;
            }
        }

        return $total + $current;
    }

    /**
     * Tập "token" để so sánh độ giống nhau giữa hai văn bản
     * (tiếng Anh: từ ≥ 3 chữ cái trừ từ phổ biến; tiếng Trung: cặp 2 chữ Hán liền nhau).
     *
     * @return array<string, true>
     */
    public static function tokens(string $text, string $language): array
    {
        $text = mb_strtolower($text);
        $out = [];

        if ($language === 'zh') {
            $han = preg_replace('/[^\p{Han}]+/u', '|', $text);
            foreach (explode('|', $han) as $run) {
                $chars = mb_str_split($run);
                for ($i = 0; $i < count($chars) - 1; $i++) {
                    $out[$chars[$i].$chars[$i + 1]] = true;
                }
            }

            return $out;
        }

        static $stop = ['the', 'and', 'you', 'are', 'for', 'that', 'this', 'with', 'have', 'was', 'what', 'but', 'not', 'your', 'can', 'they', 'his', 'her', 'she', 'him', 'its', 'our', 'all', 'how', 'yes', 'okay', 'well', 'there', 'from', 'about', 'like', 'just', 'some', 'will', 'would', 'when', 'where', 'which', 'who', 'very', 'really', 'then', 'them', 'here', 'one', 'too', 'did', 'does', 'got', 'get'];
        preg_match_all("/[a-z][a-z']{2,}/", $text, $m);
        foreach ($m[0] as $w) {
            if (! in_array($w, $stop, true)) {
                $out[$w] = true;
            }
        }

        return $out;
    }
}
