<?php

namespace App\Support\AiImport;

use Illuminate\Support\Str;

/**
 * Ghép file nghe với phần bài theo tên ghi trong tài liệu ("nghe file Track05.mp3", "CD1-Track 5", "audio 3").
 * So tên bỏ hoa thường, dấu, phần mở rộng, ký tự phân cách; khớp số thứ tự khi tên chỉ khác cách viết.
 */
class AudioLinker
{
    public static function norm(string $name): string
    {
        $base = preg_replace('/\.(mp3|m4a|wav|ogg|aac|mp4)$/i', '', basename(trim($name)));

        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($base)));
    }

    /** Số trong tên (bỏ số 0 đầu): "Track05" → [5]. */
    public static function numbers(string $name): array
    {
        preg_match_all('/\d+/', preg_replace('/\.(mp3|m4a|wav|ogg|aac|mp4)$/i', '', basename($name)), $m);

        return array_map('intval', $m[0]);
    }

    /**
     * @param  list<array{id: string, name: string}>  $audio
     * @return string|null id file khớp
     */
    public function match(?string $hint, array $audio): ?string
    {
        if (! $hint || ! $audio) {
            return null;
        }
        $h = self::norm($hint);
        if ($h === '') {
            return null;
        }
        // 1. Trùng hẳn tên
        foreach ($audio as $a) {
            if (self::norm($a['name']) === $h) {
                return $a['id'];
            }
        }
        // 2. Tên này chứa tên kia (vd "cd1track05" ⊃ "track05")
        foreach ($audio as $a) {
            $n = self::norm($a['name']);
            if ($n !== '' && (str_contains($n, $h) || str_contains($h, $n))) {
                return $a['id'];
            }
        }
        // 3. Cùng chữ + cùng số ("track 5" ↔ "Track05")
        $hNums = self::numbers($hint);
        $hWord = preg_replace('/\d+/', '', $h);
        foreach ($audio as $a) {
            $n = self::norm($a['name']);
            if ($hNums && self::numbers($a['name']) === $hNums && preg_replace('/\d+/', '', $n) === $hWord) {
                return $a['id'];
            }
        }
        // 4. Chỉ ghi số ("file 3") → file duy nhất có đúng số đó
        if ($hNums) {
            $cands = array_values(array_filter($audio, fn ($a) => array_slice(self::numbers($a['name']), -1) === array_slice($hNums, -1)));
            if (count($cands) === 1) {
                return $cands[0]['id'];
            }
        }

        return null;
    }
}
