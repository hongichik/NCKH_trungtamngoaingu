<?php

namespace App\Support\QuestionTypes;

use InvalidArgumentException;

/** Danh sách engine (giao diện làm bài + cách chấm). */
class Engines
{
    public const MAP = [
        'choice' => Engines\Choice::class,
        'multi_choice' => Engines\MultiChoice::class,
        'gap_text' => Engines\GapText::class,
        'gap_select' => Engines\GapSelect::class,
        'gap_bank' => Engines\GapBank::class,
        'matching' => Engines\Matching::class,
        'categorize' => Engines\Categorize::class,
        'ordering' => Engines\Ordering::class,
        'word_order' => Engines\WordOrder::class,
        'error_correction' => Engines\ErrorCorrection::class,
        'highlight' => Engines\Highlight::class,
        'short_text' => Engines\ShortText::class,
        'dictation' => Engines\Dictation::class,
        'hotspot' => Engines\Hotspot::class,
        'writing' => Engines\Writing::class,
        'speaking' => Engines\Speaking::class,
        'hanzi' => Engines\Hanzi::class,
    ];

    /** @var array<string, Engine> */
    private static array $cache = [];

    public static function get(string $key): Engine
    {
        if (! isset(self::MAP[$key])) {
            throw new InvalidArgumentException("Engine không tồn tại: {$key}");
        }

        return self::$cache[$key] ??= new (self::MAP[$key]);
    }

    /** @return array<string, Engine> */
    public static function all(): array
    {
        return array_map(fn ($k) => self::get($k), array_combine(array_keys(self::MAP), array_keys(self::MAP)));
    }
}
