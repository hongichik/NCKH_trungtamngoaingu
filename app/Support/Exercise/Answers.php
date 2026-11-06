<?php

namespace App\Support\Exercise;

/** Chuẩn hoá để so khớp căn cứ / đáp án (dùng chung cho kiểm định và chấm điểm). */
class Answers
{
    /** So khớp "mềm": dấu nháy, gạch, khoảng trắng, chữ thường. Tiếng Trung bỏ hẳn khoảng trắng. */
    public static function forMatch(string $s, string $language = 'en'): string
    {
        $s = str_replace(['‘', '’', '‚', '‛', '′', '“', '”', '„', '‟', '″', '–', '—', '…'], ["'", "'", "'", "'", "'", '"', '"', '"', '"', '"', '-', '-', '...'], $s);
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));

        return $language === 'zh' ? preg_replace('/\s+/u', '', $s) : $s;
    }

    /** Đáp án điền từ / viết: bỏ thêm dấu câu. */
    public static function normalize(string $s, string $language = 'en'): string
    {
        $s = self::forMatch($s, $language);
        $s = preg_replace('/[.,!?;:"()\[\]。，！？；：、（）《》]/u', ' ', $s);

        return trim(preg_replace('/\s+/u', $language === 'zh' ? '' : ' ', $s));
    }

    /** "B", "B.", "B. text", "(B)" → "B". */
    public static function letter(string $s): string
    {
        return preg_match('/^\(?([A-Ha-h])[.):]?(\s|$)/', trim($s), $m) ? strtoupper($m[1]) : strtoupper(trim($s));
    }

    /** "c-a-e-b-d" / "c, a, e" → "CAEBD". */
    public static function ordering(string $s): string
    {
        preg_match_all('/[A-Ha-h]/', $s, $m);

        return strtoupper(implode('', $m[0]));
    }
}
