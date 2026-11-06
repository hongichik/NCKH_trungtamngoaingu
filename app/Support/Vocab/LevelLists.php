<?php

namespace App\Support\Vocab;

/**
 * Danh sách từ theo trình độ (file tĩnh trong resources/data, không tốn AI):
 *  - hsk.json: từ HSK 2.0 cấp 1–6 (+ pinyin chuẩn);
 *  - cefr-en.json: từ tiếng Anh theo CEFR A1–B2 (CEFR-J);
 *  - hanzi.json: chữ Hán → các cách đọc pinyin, bộ thủ.
 * Dùng để cảnh báo từ vượt trình độ của khoá và kiểm tra pinyin có khớp chữ Hán.
 */
class LevelLists
{
    public const CEFR = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6];

    private static array $cache = [];

    private static function load(string $file, string $key): array
    {
        return self::$cache[$file] ??= (json_decode((string) @file_get_contents(resource_path("data/{$file}")), true)[$key] ?? []);
    }

    public static function hsk(): array
    {
        return self::load('hsk.json', 'words');
    }

    public static function cefr(): array
    {
        return self::load('cefr-en.json', 'words');
    }

    public static function hanzi(): array
    {
        return self::load('hanzi.json', 'chars');
    }

    /** Cấp của khoá theo chuỗi trình độ giáo trình: "B1", "A2+", "HSK 3", "3"… → số (CEFR 1–6 / HSK 1–6) hoặc null. */
    public static function courseLevel(?string $level, string $language): ?int
    {
        $level = strtoupper(trim((string) $level));
        if ($language === 'zh') {
            return preg_match('/(\d)/', $level, $m) ? max(1, min(6, (int) $m[1])) : null;
        }

        return preg_match('/([ABC][12])/', $level, $m) ? self::CEFR[$m[1]] : null;
    }

    public static function levelLabel(?int $n, string $language): string
    {
        if (! $n) {
            return 'ngoài danh sách';
        }

        return $language === 'zh' ? "HSK {$n}" : (array_search($n, self::CEFR, true) ?: '?');
    }

    /** Cấp HSK của từ tiếng Trung (null = không có trong HSK 1–6). */
    public static function hskLevel(string $word): ?int
    {
        return self::hsk()[trim($word)][0] ?? null;
    }

    /** Cấp CEFR (1–4) của từ / cụm tiếng Anh; cụm từ lấy cấp cao nhất của các từ có nghĩa. */
    public static function cefrLevel(string $word): ?int
    {
        $w = mb_strtolower(trim($word));
        $list = self::cefr();
        if (isset($list[$w])) {
            return self::CEFR[$list[$w]];
        }
        if (str_contains($w, ' ')) {
            $max = null;
            foreach (preg_split('/\s+/', $w) as $part) {
                if (strlen($part) < 3 || in_array($part, ['the', 'and', 'for', 'with', 'from'], true)) {
                    continue;
                }
                $l = self::cefrLevel($part);
                if ($l === null) {
                    return null;
                }
                $max = max($max ?? 0, $l);
            }

            return $max;
        }
        foreach (self::lemmas($w) as $lemma) {
            if (isset($list[$lemma])) {
                return self::CEFR[$list[$lemma]];
            }
        }

        return null;
    }

    /** Dạng gốc đơn giản: books→book, studies→study, played→play, making→make, bigger→big. */
    private static function lemmas(string $w): array
    {
        $w = preg_replace("/[^a-z'-]/", '', $w);
        $out = [];
        foreach ([['ies', 'y'], ['es', ''], ['s', ''], ['ied', 'y'], ['ed', ''], ['ed', 'e'], ['ing', ''], ['ing', 'e'], ['er', ''], ['est', ''], ['ier', 'y'], ['iest', 'y'], ['ly', '']] as [$suf, $rep]) {
            if (strlen($w) > strlen($suf) + 2 && str_ends_with($w, $suf)) {
                $base = substr($w, 0, -strlen($suf)).$rep;
                $out[] = $base;
                if (preg_match('/(.)\1$/', $base)) {
                    $out[] = substr($base, 0, -1); // stopped → stop, bigger → big
                }
            }
        }

        return $out;
    }

    /** Cấp của từ theo ngôn ngữ. */
    public static function wordLevel(string $word, string $language): ?int
    {
        return $language === 'zh' ? self::hskLevel($word) : self::cefrLevel($word);
    }

    /** Bỏ dấu thanh: "péngyou" → "pengyou", "nǚ" → "nü". */
    public static function stripTones(string $pinyin): string
    {
        $map = ['ā' => 'a', 'á' => 'a', 'ǎ' => 'a', 'à' => 'a', 'ē' => 'e', 'é' => 'e', 'ě' => 'e', 'è' => 'e', 'ī' => 'i', 'í' => 'i', 'ǐ' => 'i', 'ì' => 'i',
            'ō' => 'o', 'ó' => 'o', 'ǒ' => 'o', 'ò' => 'o', 'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u', 'ǖ' => 'ü', 'ǘ' => 'ü', 'ǚ' => 'ü', 'ǜ' => 'ü', 'v' => 'ü'];

        return strtr(mb_strtolower($pinyin), $map);
    }

    /**
     * Kiểm tra pinyin có khớp chữ Hán: 'ok' | 'tone' (đúng âm, khác thanh so với HSK) | 'mismatch' | 'unknown'.
     */
    public static function checkPinyin(string $word, string $pinyin): string
    {
        $word = trim($word);
        $flat = preg_replace('/[^a-zü]/u', '', self::stripTones($pinyin));
        $chars = preg_split('//u', preg_replace('/[^\p{Han}]/u', '', $word), -1, PREG_SPLIT_NO_EMPTY);
        if (! $chars || $flat === '') {
            return 'unknown';
        }
        $dict = self::hanzi();
        $readings = [];
        foreach ($chars as $c) {
            if (! isset($dict[$c])) {
                return 'unknown';
            }
            $readings[] = array_unique(array_map(fn ($p) => preg_replace('/[^a-zü]/u', '', self::stripTones($p)), $dict[$c][0]));
        }
        if (! self::matchReadings($readings, 0, $flat)) {
            return 'mismatch';
        }
        $std = self::hsk()[$word][1] ?? null;
        if ($std && self::compactPinyin($std) !== self::compactPinyin($pinyin)) {
            return 'tone';
        }

        return 'ok';
    }

    private static function compactPinyin(string $p): string
    {
        return preg_replace('/[^\p{L}]/u', '', mb_strtolower(str_replace('v', 'ü', $p)));
    }

    private static function matchReadings(array $readings, int $i, string $rest): bool
    {
        if ($i === count($readings)) {
            return $rest === '';
        }
        foreach ($readings[$i] as $r) {
            if ($r !== '' && str_starts_with($rest, $r) && self::matchReadings($readings, $i + 1, substr($rest, strlen($r)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Từ vượt trình độ trong một đoạn văn bản (đề bài, đáp án…).
     *
     * @return array<string, int> từ => cấp
     */
    public static function aboveLevel(string $text, string $language, int $courseLevel): array
    {
        $found = [];
        if ($language === 'zh') {
            // Tách từ tham lam theo danh sách HSK (từ dài nhất trước, tối đa 4 chữ)
            $hsk = self::hsk();
            $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
            for ($i = 0, $n = count($chars); $i < $n;) {
                $hit = 1;
                for ($len = min(4, $n - $i); $len >= 1; $len--) {
                    $w = implode('', array_slice($chars, $i, $len));
                    if (isset($hsk[$w])) {
                        if ($hsk[$w][0] > $courseLevel) {
                            $found[$w] = $hsk[$w][0];
                        }
                        $hit = $len;
                        break;
                    }
                }
                $i += $hit;
            }

            return $found;
        }
        preg_match_all("/[A-Za-z][A-Za-z'-]{2,}/", $text, $m);
        foreach (array_unique(array_map('strtolower', $m[0])) as $w) {
            $l = self::cefrLevel($w);
            if ($l !== null && $l > $courseLevel) {
                $found[$w] = $l;
            }
        }

        return $found;
    }
}
