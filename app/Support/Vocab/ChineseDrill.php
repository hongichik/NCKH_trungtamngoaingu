<?php

namespace App\Support\Vocab;

/**
 * Bài luyện tiếng Trung sinh bằng code từ từ vựng Unit (không tốn AI), chấm trên trình duyệt:
 *  tone      — chọn pinyin đúng thanh điệu;
 *  listen    — nghe, chọn pinyin;
 *  hanzi     — xem pinyin + nghĩa, chọn chữ Hán;
 *  radical   — chọn bộ thủ của chữ;
 *  dictation — nghe, gõ chữ Hán.
 */
class ChineseDrill
{
    public const TYPES = ['tone' => 'Chọn thanh điệu', 'listen' => 'Nghe chọn pinyin', 'hanzi' => 'Pinyin → chữ Hán', 'radical' => 'Nhận bộ thủ', 'dictation' => 'Nghe viết chữ Hán'];

    private const TONES = [
        'a' => ['ā', 'á', 'ǎ', 'à'], 'e' => ['ē', 'é', 'ě', 'è'], 'i' => ['ī', 'í', 'ǐ', 'ì'],
        'o' => ['ō', 'ó', 'ǒ', 'ò'], 'u' => ['ū', 'ú', 'ǔ', 'ù'], 'ü' => ['ǖ', 'ǘ', 'ǚ', 'ǜ'],
    ];

    /** @param  list<array{word: string, pinyin: string, meaning: string}>  $words */
    public function build(array $words, int $count = 15): array
    {
        $words = array_values(array_filter($words, fn ($w) => preg_match('/\p{Han}/u', $w['word']) && trim($w['pinyin']) !== ''));
        if (count($words) < 2) {
            return [];
        }
        shuffle($words);
        $dict = LevelLists::hanzi();
        $types = array_keys(self::TYPES);
        $out = [];
        $i = 0;
        foreach (array_merge($words, $words) as $w) {
            if (count($out) >= $count) {
                break;
            }
            $type = $types[$i++ % count($types)];
            $q = match ($type) {
                'tone' => $this->tone($w),
                'listen' => $this->pick($w, $words, 'pinyin', ['audio' => $w['word'], 'prompt' => 'Nghe và chọn pinyin đúng']),
                'hanzi' => $this->pick($w, $words, 'word', ['prompt' => trim($w['pinyin'].' — '.$w['meaning'], ' —')]),
                'radical' => $this->radical($w, $dict),
                'dictation' => ['audio' => $w['word'], 'prompt' => 'Nghe và gõ chữ Hán', 'answer' => $w['word'], 'hint' => $w['meaning']],
            };
            if ($q) {
                $out[] = ['type' => $type, 'label' => self::TYPES[$type]] + $q;
            }
        }

        return $out;
    }

    private function pick(array $w, array $all, string $field, array $extra): ?array
    {
        $others = array_values(array_unique(array_filter(array_column($all, $field), fn ($v) => $v !== $w[$field])));
        if (count($others) < 1) {
            return null;
        }
        shuffle($others);
        $options = array_merge([$w[$field]], array_slice($others, 0, 3));
        shuffle($options);

        return $extra + ['options' => $options, 'answer' => $w[$field], 'reveal' => $w['word'].' · '.$w['pinyin'].' · '.$w['meaning']];
    }

    /** Đổi thanh điệu các nguyên âm có dấu → các phương án nhiễu. */
    private function tone(array $w): ?array
    {
        $correct = trim($w['pinyin']);
        $variants = [];
        for ($try = 0; $try < 30 && count($variants) < 3; $try++) {
            $v = $this->retone($correct);
            if ($v !== $correct && ! in_array($v, $variants, true)) {
                $variants[] = $v;
            }
        }
        if (count($variants) < 2) {
            return null;
        }
        $options = array_merge([$correct], $variants);
        shuffle($options);

        return ['prompt' => $w['word'].($w['meaning'] ? ' ('.$w['meaning'].')' : ''), 'big' => $w['word'], 'audio' => $w['word'], 'options' => $options, 'answer' => $correct, 'reveal' => $w['word'].' · '.$correct];
    }

    private function retone(string $p): string
    {
        $chars = preg_split('//u', $p, -1, PREG_SPLIT_NO_EMPTY);
        $marked = [];
        foreach ($chars as $idx => $c) {
            foreach (self::TONES as $base => $tones) {
                if (in_array($c, $tones, true)) {
                    $marked[$idx] = $base;
                }
            }
        }
        if (! $marked) {
            return $p;
        }
        $idx = array_rand($marked);
        $chars[$idx] = self::TONES[$marked[$idx]][random_int(0, 3)];

        return implode('', $chars);
    }

    private function radical(array $w, array $dict): ?array
    {
        foreach (preg_split('//u', $w['word'], -1, PREG_SPLIT_NO_EMPTY) as $c) {
            $rad = $dict[$c][1] ?? '';
            if ($rad === '' || $rad === $c) {
                continue; // chữ tự là bộ thủ → bỏ
            }
            $pool = ['氵', '亻', '口', '木', '女', '扌', '艹', '讠', '心', '忄', '日', '月', '土', '纟', '宀', '辶', '火', '金', '钅', '目', '言', '贝', '力', '人', '又', '子', '门', '马', '饣', '衤'];
            $pool = array_values(array_diff($pool, [$rad]));
            shuffle($pool);
            $options = array_merge([$rad], array_slice($pool, 0, 3));
            shuffle($options);

            return ['prompt' => 'Bộ thủ của chữ này là gì?', 'big' => $c, 'options' => $options, 'answer' => $rad, 'reveal' => $c.' — bộ '.$rad.' (trong từ '.$w['word'].')'];
        }

        return null;
    }
}
